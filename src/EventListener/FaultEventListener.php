<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\EventListener;

use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Psr\Log\LoggerInterface;
use SoapFault;

/**
 * Maps SOAP faults from the EU VAT service to domain-specific exceptions
 *
 * The client calls this directly from its SOAP fault catch block to transform
 * faults into domain-specific exceptions with enhanced error context.
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
 *   → InvalidRequestException, but only with evidence that the fault came off
 *     the wire (see below); without that evidence → ServiceUnavailableException
 * - faultcode local part `Server` (SOAP 1.1) or `Receiver` (SOAP 1.2)
 *   → ServiceUnavailableException
 * - anything else → SoapFaultException
 *
 * The evidence requirement exists because ext-soap raises its *own* local
 * failures as SOAP 1.1 client faults, with a bare (unprefixed) `Client` fault
 * code and a faultstring of its own making:
 *
 * ```
 * SoapFault('Client', 'looks like we got no XML document')       // non-XML response body
 * SoapFault('Client', 'DTD are not supported by SOAP')           // HTML error page
 * SoapFault('Client', 'SoapClient::__doRequest() returned non string value')
 * ```
 *
 * Those never reached the service - a proxy, WAF, captive portal or truncated
 * response caused them - so reporting them as a request rejection is wrong twice
 * over: the message blames the caller's input, and InvalidRequestException tells
 * the caller not to retry a fault that is usually transient. A client fault is
 * therefore only treated as a service rejection when there is positive evidence
 * for it: a namespace-prefixed fault code as the service sends it (`env:Client`),
 * or a `TEDB-…` identifier in the faultstring. Everything else with a client
 * local part surfaces as ServiceUnavailableException, which the exception
 * contract documents as retryable.
 *
 * A `TEDB-…` identifier found at the start of the faultstring (e.g. `TEDB-ERR-2`)
 * is used as the exception error code, and the per-error descriptions from the
 * fault detail are appended to the exception message. Both are best-effort: a
 * faultstring without a TEDB identifier or a detail in an unexpected shape
 * degrades to the raw fault code and an empty description list rather than
 * failing.
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
final class FaultEventListener
{
    /**
     * SOAP faultcode local parts that place responsibility on the caller
     *
     * `Client` is the SOAP 1.1 spelling, `Sender` the SOAP 1.2 one.
     */
    private const array CLIENT_FAULT_CODES = ['client', 'sender'];

    /**
     * SOAP faultcode local parts that place responsibility on the service
     *
     * `Server` is the SOAP 1.1 spelling, `Receiver` the SOAP 1.2 one.
     */
    private const array SERVER_FAULT_CODES = ['server', 'receiver'];

    /**
     * SOAP envelope namespaces a fault code may legitimately be bound to
     *
     * Used to reject fault codes whose local part reads like a responsibility
     * marker but belongs to an unrelated namespace.
     */
    private const array SOAP_ENVELOPE_NAMESPACES = [
        'http://schemas.xmlsoap.org/soap/envelope/', // SOAP 1.1
        'http://www.w3.org/2003/05/soap-envelope',   // SOAP 1.2
    ];

    /**
     * Message used when a fault carries no usable fault string
     */
    private const string NO_FAULT_STRING = 'No fault string provided';

    /**
     * Maximum number of nested levels walked when collecting fault detail errors
     */
    private const int DETAIL_MAX_DEPTH = 10;

    /**
     * Maximum number of error descriptions collected from a single fault detail
     */
    private const int DETAIL_MAX_ERRORS = 20;

    /**
     * Create fault event listener with logger
     *
     * @param LoggerInterface $logger PSR-3 logger for fault recording.
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
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
     *         (faultcode `env:Server`/`Receiver`), and for client faults ext-soap
     *         raised locally without ever reaching the service.
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
        $faultCode = $this->normaliseFaultCode($fault->faultcode ?? null);
        $faultString = $this->normaliseFaultString($fault->faultstring ?? null);
        $faultDetail = $fault->detail ?? null;

        // Log comprehensive fault information for debugging
        $this->logger->error('SOAP Fault received from EU VAT service', [
            'fault_code' => $faultCode,
            'fault_string' => $faultString,
            'fault_detail' => $faultDetail,
            'fault_actor' => $fault->faultactor ?? null,
        ]);

        // The TEDB identifier travels in the faultstring; fall back to the raw fault code
        $tedbCode = $this->extractTedbCode($faultString);
        $errorCode = $tedbCode ?? $faultCode;
        $context = $this->describeServiceErrors($faultDetail);
        $faultCodeNamespace = $fault->faultcodens ?? null;

        if ($this->attributesFaultToClient($faultCode, $faultCodeNamespace)) {
            if ($this->isServiceAttributed($faultCode, $tedbCode)) {
                throw new InvalidRequestException(
                    "Invalid request rejected by the EU VAT service ({$errorCode}): {$faultString}{$context}",
                    $errorCode,
                    $fault
                );
            }

            // A bare client fault code without a TEDB identifier is how ext-soap
            // reports its own local failures, so no request was ever rejected.
            throw new ServiceUnavailableException(
                "Communication with the EU VAT service failed before a service response could be read "
                . "({$errorCode}): {$faultString}{$context}",
                $errorCode,
                $fault
            );
        }

        if ($this->attributesFaultToServer($faultCode, $faultCodeNamespace)) {
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
        return $this->attributesFaultToClient($faultCode, null);
    }

    /**
     * Check if a SOAP fault code attributes the failure to the service
     *
     * @param string $faultCode SOAP fault code to check, e.g. `env:Server`.
     * @return boolean True if the service blames itself.
     */
    public function isServerError(string $faultCode): bool
    {
        return $this->attributesFaultToServer($faultCode, null);
    }

    /**
     * Check whether a fault code is a SOAP responsibility marker blaming the caller
     *
     * @param string      $faultCode          Raw fault code, e.g. `env:Client`.
     * @param string|null $faultCodeNamespace Namespace ext-soap resolved for the code.
     */
    private function attributesFaultToClient(string $faultCode, ?string $faultCodeNamespace): bool
    {
        return $this->isResponsibilityMarker($faultCode, $faultCodeNamespace, self::CLIENT_FAULT_CODES);
    }

    /**
     * Check whether a fault code is a SOAP responsibility marker blaming the service
     *
     * @param string      $faultCode          Raw fault code, e.g. `env:Server`.
     * @param string|null $faultCodeNamespace Namespace ext-soap resolved for the code.
     */
    private function attributesFaultToServer(string $faultCode, ?string $faultCodeNamespace): bool
    {
        return $this->isResponsibilityMarker($faultCode, $faultCodeNamespace, self::SERVER_FAULT_CODES);
    }

    /**
     * Check a fault code against a set of SOAP responsibility markers
     *
     * Namespace handling, deliberately: a fault code is a QName, but ext-soap does
     * not preserve the prefix binding, so the namespace of a prefixed code such as
     * `env:Client` cannot be resolved after the fact. What is available is
     * SoapFault::$faultcodens, which ext-soap populates only for unprefixed codes.
     * The accepted shapes are therefore:
     *
     * - a prefixed code (`env:Client`, `soap:Sender`) - the prefix is taken on
     *   trust, since a fault code has no meaning outside the envelope namespace
     *   and ext-soap never prefixes the fault codes it raises itself;
     * - an unprefixed code (`Client`) whose $faultcodens is empty or one of the
     *   SOAP 1.1/1.2 envelope namespaces.
     *
     * An unprefixed code bound to an unrelated namespace is rejected: its local
     * part looks like a responsibility marker but means something else.
     *
     * @param string        $faultCode          Raw fault code.
     * @param string|null   $faultCodeNamespace Namespace ext-soap resolved for the code.
     * @param list<string>  $markers            Lower-cased local parts to match.
     */
    private function isResponsibilityMarker(string $faultCode, ?string $faultCodeNamespace, array $markers): bool
    {
        if (!in_array($this->faultCodeLocalPart($faultCode), $markers, true)) {
            return false;
        }

        if ($this->isPrefixed($faultCode)) {
            return true;
        }

        return $faultCodeNamespace === null
            || $faultCodeNamespace === ''
            || in_array($faultCodeNamespace, self::SOAP_ENVELOPE_NAMESPACES, true);
    }

    /**
     * Check whether a client fault carries evidence that the service produced it
     *
     * The service sends its fault code as a QName (`env:Client`) and prefixes the
     * faultstring with a TEDB identifier. ext-soap's local failures have neither:
     * a bare `Client` code and a faultstring of ext-soap's own wording.
     *
     * @param string      $faultCode Raw fault code.
     * @param string|null $tedbCode  TEDB identifier extracted from the faultstring, if any.
     */
    private function isServiceAttributed(string $faultCode, ?string $tedbCode): bool
    {
        if ($this->isPrefixed($faultCode)) {
            return true;
        }
        return $tedbCode !== null;
    }

    /**
     * Check whether a fault code carries a namespace prefix
     *
     * @param string $faultCode Raw fault code.
     */
    private function isPrefixed(string $faultCode): bool
    {
        return str_contains(trim($faultCode), ':');
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

        return strtolower(trim(end($segments)));
    }

    /**
     * Normalise the fault code, falling back when the fault carries none
     *
     * @param string|null $faultCode Raw fault code, if the fault has one.
     * @return string Fault code, or `UNKNOWN` when absent or blank.
     */
    private function normaliseFaultCode(?string $faultCode): string
    {
        return ($faultCode === null || trim($faultCode) === '') ? 'UNKNOWN' : $faultCode;
    }

    /**
     * Normalise the fault string, falling back when the fault carries none
     *
     * An empty fault string is as useless as an absent one and would otherwise
     * leave the exception message ending in a dangling colon.
     *
     * @param string|null $faultString Raw fault string, if the fault has one.
     * @return string Fault string, or a placeholder when absent or blank.
     */
    private function normaliseFaultString(?string $faultString): string
    {
        return trim($faultString ?? '') === '' ? self::NO_FAULT_STRING : $faultString;
    }

    /**
     * Extract the TEDB error identifier from a fault string
     *
     * The service puts the identifier at the very start of the fault string, as in
     * `TEDB-ERR-2 - Request is not valid` (see the recorded cassettes). The match is
     * anchored there so that a fault string merely mentioning an identifier does not
     * supply a bogus error code. Returns null when no identifier is present, so a
     * changed fault string format cannot break fault handling.
     *
     * @param string $faultString Fault string as sent by the service.
     * @return string|null Normalised identifier such as `TEDB-ERR-2`, or null.
     */
    private function extractTedbCode(string $faultString): ?string
    {
        if (preg_match('/^\s*(TEDB(?:-[A-Z0-9]+)+)\b/i', $faultString, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
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
}
