<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\EventListener;

use DOMDocument;
use Netresearch\EuVatSdk\Engine\SoapFaultEvent;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Psr\Log\LoggerInterface;
use SoapFault;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener for mapping SOAP faults to domain-specific exceptions
 *
 * This listener intercepts SOAP faults from the EU VAT service and transforms
 * them into domain-specific exceptions with enhanced error context.
 *
 * The mapping is derived from the fault shapes the TEDB service actually
 * produces. A rejected request looks like this on the wire:
 *
 * ```xml
 * <env:Fault>
 *   <faultcode>env:Client</faultcode>
 *   <faultstring>TEDB-ERR-2 - Request is not valid</faultstring>
 *   <detail>
 *     <ns2:retrieveVatRatesFaultMsg>
 *       <ns0:error>
 *         <ns0:code>00002</ns0:code>
 *         <ns0:description>The Member State "XX" does not exist.</ns0:description>
 *       </ns0:error>
 *     </ns2:retrieveVatRatesFaultMsg>
 *   </detail>
 * </env:Fault>
 * ```
 *
 * The TEDB error identifier therefore lives in the faultstring, not in the
 * faultcode; the faultcode only carries the standard SOAP responsibility
 * marker. Classification consequently keys on that marker:
 *
 * - faultcode local part `Client` (SOAP 1.1) or `Sender` (SOAP 1.2)
 *   → InvalidRequestException
 * - faultcode local part `Server` (SOAP 1.1) or `Receiver` (SOAP 1.2)
 *   → ServiceUnavailableException
 * - anything else → SoapFaultException
 *
 * A `TEDB-…` identifier found in the faultstring (e.g. `TEDB-ERR-2`) is used as
 * the exception error code, and the per-error descriptions from the fault detail
 * are appended to the exception message. Both are best-effort: a faultstring
 * without a TEDB identifier or a detail in an unexpected shape degrades to the
 * raw fault code and an empty description list rather than failing.
 *
 * @example Integration with SOAP client:
 * ```php
 * try {
 *     $response = $soapClient->call($method, $arguments);
 * } catch (SoapFault $fault) {
 *     $listener = new FaultEventListener($logger);
 *     $listener->handleSoapFault($fault); // Throws domain exception
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\EventListener
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class FaultEventListener implements EventSubscriberInterface
{
    /**
     * SOAP faultcode local parts that place responsibility on the caller
     *
     * `Client` is the SOAP 1.1 spelling, `Sender` the SOAP 1.2 one.
     */
    private const CLIENT_FAULT_CODES = ['client', 'sender'];

    /**
     * SOAP faultcode local parts that place responsibility on the service
     *
     * `Server` is the SOAP 1.1 spelling, `Receiver` the SOAP 1.2 one.
     */
    private const SERVER_FAULT_CODES = ['server', 'receiver'];

    /**
     * Maximum number of nested levels walked when collecting fault detail errors
     */
    private const DETAIL_MAX_DEPTH = 10;

    /**
     * Maximum number of error descriptions collected from a single fault detail
     */
    private const DETAIL_MAX_ERRORS = 20;

    /**
     * Create fault event listener with logger
     *
     * @param LoggerInterface $logger PSR-3 logger for fault recording.
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Get subscribed events for Symfony EventDispatcher
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SoapFaultEvent::NAME => 'onSoapFault',
        ];
    }

    /**
     * Handle SOAP fault event
     *
     * @param SoapFaultEvent $event The fault event from EventAwareEngine
     */
    public function onSoapFault(SoapFaultEvent $event): void
    {
        $exception = $event->getException();
        if ($exception instanceof SoapFault) {
            $this->handleSoapFault($exception);
        }
    }

    /**
     * Handle SOAP fault by mapping to domain exception
     *
     * This method analyzes the SOAP fault and creates an appropriate domain
     * exception with enhanced error context. The original fault is preserved
     * as the previous exception for full stack trace information.
     *
     * @param SoapFault $fault Original SOAP fault from service.
     * @throws InvalidRequestException For faults the service attributes to the caller
     *         (faultcode `env:Client`/`Sender`), e.g. `TEDB-ERR-2 - Request is not valid`.
     * @throws ServiceUnavailableException For faults the service attributes to itself
     *         (faultcode `env:Server`/`Receiver`).
     * @throws SoapFaultException For any other, unrecognised SOAP fault.
     *
     * @example Fault handling in client:
     * ```php
     * try {
     *     $result = $this->soapClient->retrieveVatRates($request);
     * } catch (SoapFault $fault) {
     *     $this->faultListener->handleSoapFault($fault);
     * }
     * ```
     *
     */
    public function handleSoapFault(SoapFault $fault): void
    {
        $faultCode = $fault->faultcode ?? 'UNKNOWN';
        $faultString = $fault->faultstring ?? 'No fault string provided';
        $faultDetail = $fault->detail ?? null;

        // Log comprehensive fault information for debugging
        $this->logger->error('SOAP Fault received from EU VAT service', [
            'fault_code' => $faultCode,
            'fault_string' => $faultString,
            'fault_detail' => $faultDetail,
            'fault_actor' => $fault->faultactor ?? null,
        ]);

        // The TEDB identifier travels in the faultstring; fall back to the raw fault code
        $errorCode = $this->extractTedbCode($faultString) ?? $faultCode;
        $context = $this->describeServiceErrors($faultDetail);

        if ($this->isClientValidationError($faultCode)) {
            throw new InvalidRequestException(
                "Invalid request rejected by the EU VAT service ({$errorCode}): {$faultString}{$context}",
                $errorCode,
                $fault
            );
        }

        if ($this->isServerError($faultCode)) {
            throw new ServiceUnavailableException(
                "Internal error in the EU VAT service ({$errorCode}): {$faultString}{$context}",
                $errorCode,
                $fault
            );
        }

        // Unrecognised SOAP faults - preserve original fault information
        throw new SoapFaultException(
            "SOAP fault occurred ({$faultCode}): {$faultString}{$context}",
            $faultCode,
            $faultString,
            $fault
        );
    }

    /**
     * Check if a SOAP fault code attributes the failure to the caller
     *
     * Compares the local part of the fault code, so namespace-prefixed codes such
     * as `env:Client` are recognised just like the bare `Client`.
     *
     * @param string $faultCode SOAP fault code to check, e.g. `env:Client`.
     * @return boolean True if the service blames the request.
     *
     * @example Usage in error categorization:
     * ```php
     * if ($listener->isClientValidationError($faultCode)) {
     *     // Handle as user input error
     * } else {
     *     // Handle as system/service error
     * }
     * ```
     */
    public function isClientValidationError(string $faultCode): bool
    {
        return in_array($this->faultCodeLocalPart($faultCode), self::CLIENT_FAULT_CODES, true);
    }

    /**
     * Check if a SOAP fault code attributes the failure to the service
     *
     * @param string $faultCode SOAP fault code to check, e.g. `env:Server`.
     * @return boolean True if the service blames itself.
     */
    public function isServerError(string $faultCode): bool
    {
        return in_array($this->faultCodeLocalPart($faultCode), self::SERVER_FAULT_CODES, true);
    }

    /**
     * Reduce a SOAP fault code to its lower-cased local part
     *
     * `env:Client` and `Client` both become `client`.
     *
     * @param string $faultCode Raw fault code as sent by the service.
     * @return string Lower-cased local part.
     */
    private function faultCodeLocalPart(string $faultCode): string
    {
        $segments = explode(':', $faultCode);

        return strtolower(trim((string) end($segments)));
    }

    /**
     * Extract the TEDB error identifier from a fault string
     *
     * The service prefixes its fault strings with the identifier, for example
     * `TEDB-ERR-2 - Request is not valid`. Returns null when no identifier is
     * present, so a changed fault string format cannot break fault handling.
     *
     * @param string $faultString Fault string as sent by the service.
     * @return string|null Normalised identifier such as `TEDB-ERR-2`, or null.
     */
    private function extractTedbCode(string $faultString): ?string
    {
        if (preg_match('/\bTEDB(?:-[A-Z0-9]+)+\b/i', $faultString, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[0]);
    }

    /**
     * Render the per-error descriptions from a fault detail as a message suffix
     *
     * @param mixed $faultDetail Raw fault detail from the SoapFault.
     * @return string Empty string when no description could be extracted.
     */
    private function describeServiceErrors(mixed $faultDetail): string
    {
        $descriptions = [];
        $this->collectErrorDescriptions($faultDetail, $descriptions, 0);

        if ($descriptions === []) {
            return '';
        }

        return ' (' . implode('; ', $descriptions) . ')';
    }

    /**
     * Recursively collect `code`/`description` pairs from a decoded fault detail
     *
     * The decoded detail nests as retrieveVatRatesFaultMsg → error → code/description,
     * where `error` is an array for multiple errors but a single object for one. The
     * walk is therefore shape-agnostic and bounded in both depth and result count.
     *
     * @param mixed         $node         Current node of the decoded detail.
     * @param list<string>  $descriptions Collected descriptions, appended in place.
     * @param integer       $depth        Current recursion depth.
     */
    private function collectErrorDescriptions(mixed $node, array &$descriptions, int $depth): void
    {
        if ($depth > self::DETAIL_MAX_DEPTH || count($descriptions) >= self::DETAIL_MAX_ERRORS) {
            return;
        }

        if (is_object($node)) {
            $node = get_object_vars($node);
        }

        if (!is_array($node)) {
            return;
        }

        $description = $node['description'] ?? null;
        if (is_string($description) && trim($description) !== '') {
            $code = $node['code'] ?? null;
            $descriptions[] = is_scalar($code)
                ? sprintf('[%s] %s', $code, trim($description))
                : trim($description);
            return;
        }

        foreach ($node as $child) {
            $this->collectErrorDescriptions($child, $descriptions, $depth + 1);
        }
    }

    /**
     * Extract structured error details from fault detail property
     *
     * The SOAP fault detail can contain structured XML with additional
     * error information. This method attempts to extract useful details.
     *
     * @param mixed $faultDetail Raw fault detail from SoapFault.
     * @return array<string, mixed> Extracted error details.
     */
    public function extractErrorDetails(mixed $faultDetail): array
    {
        if ($faultDetail === null) {
            return [];
        }

        // If detail is already an array or object, return as-is
        if (is_array($faultDetail) || is_object($faultDetail)) {
            return (array) $faultDetail;
        }

        // If detail is a string, try to parse as XML
        if (is_string($faultDetail)) {
            // External entity loading is disabled by default since PHP 8.0 (XXE protection)
            $previousUseErrors = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $xmlParsed = false;

            try {
                // Load XML without LIBXML_NOENT to prevent entity substitution
                $xmlParsed = $dom->loadXML($faultDetail);
            } finally {
                // Always restore previous settings, even if an exception occurs
                libxml_use_internal_errors($previousUseErrors);
            }

            if ($xmlParsed) {
                // Successfully parsed as XML - extract key information
                $details = [];
                if ($dom->documentElement instanceof \DOMElement) {
                    $details['element_name'] = $dom->documentElement->nodeName;
                    $details['text_content'] = trim($dom->documentElement->textContent);
                }
                return $details;
            }

            // Not valid XML, return as plain text
            return ['raw_detail' => $faultDetail];
        }

        // Fallback for other types
        return ['raw_detail' => $faultDetail];
    }
}
