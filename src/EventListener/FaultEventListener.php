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
 * them into domain-specific exceptions with enhanced error context. It maps
 * specific fault codes to appropriate exception types based on the service
 * documentation.
 *
 * Fault Code Mappings:
 * - TEDB-100, TEDB-101, TEDB-102: Client-side validation errors → InvalidRequestException
 * - TEDB-400: Server-side internal errors → ServiceUnavailableException
 * - Other faults: Generic SOAP errors → SoapFaultException
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
     * @throws InvalidRequestException For client-side validation errors (TEDB-100, 101, 102).
     * @throws ServiceUnavailableException For server-side errors (TEDB-400).
     * @throws SoapFaultException For unhandled SOAP faults.
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

        // Map fault codes to domain-specific exceptions based on EU service documentation
        $exception = match ($faultCode) {
            // Client-side validation errors
            'TEDB-100' => new InvalidRequestException(
                "Invalid date format provided (TEDB-100): {$faultString}",
                $faultCode,
                $fault
            ),
            'TEDB-101' => new InvalidRequestException(
                "Invalid country code provided (TEDB-101): {$faultString}",
                $faultCode,
                $fault
            ),
            'TEDB-102' => new InvalidRequestException(
                "Empty member states array provided (TEDB-102): {$faultString}",
                $faultCode,
                $fault
            ),

            // Server-side internal errors
            'TEDB-400' => new ServiceUnavailableException(
                "Internal application error in EU VAT service (TEDB-400): {$faultString}",
                $faultCode,
                $fault
            ),

            // Unhandled SOAP faults - preserve original fault information
            default => new SoapFaultException(
                "SOAP fault occurred ({$faultCode}): {$faultString}",
                $faultCode,
                $faultString,
                $fault
            )
        };

        throw $exception;
    }

    /**
     * Check if a fault code represents a client-side validation error
     *
     * @param string $faultCode Fault code to check.
     * @return boolean True if this is a client validation error.
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
        return in_array($faultCode, ['TEDB-100', 'TEDB-101', 'TEDB-102'], true);
    }

    /**
     * Check if a fault code represents a server-side error
     *
     * @param string $faultCode Fault code to check.
     * @return boolean True if this is a server-side error.
     */
    public function isServerError(string $faultCode): bool
    {
        return $faultCode === 'TEDB-400';
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
            // Protect against XXE attacks by disabling external entity loading
            $previousUseErrors = libxml_use_internal_errors(true);
            $previousEntityLoader = libxml_disable_entity_loader(true);
            $dom = new DOMDocument();
            $xmlParsed = false;

            try {
                // Load XML without LIBXML_NOENT to prevent entity substitution
                $xmlParsed = $dom->loadXML($faultDetail);
            } finally {
                // Always restore previous settings, even if an exception occurs
                libxml_disable_entity_loader($previousEntityLoader);
            }

            if ($xmlParsed) {
                // Successfully parsed as XML - extract key information
                $details = [];
                if ($dom->documentElement instanceof \DOMElement) {
                    $details['element_name'] = $dom->documentElement->nodeName;
                    $details['text_content'] = trim($dom->documentElement->textContent);
                }
                libxml_use_internal_errors($previousUseErrors);
                return $details;
            }

            libxml_use_internal_errors($previousUseErrors);

            // Not valid XML, return as plain text
            return ['raw_detail' => $faultDetail];
        }

        // Fallback for other types
        return ['raw_detail' => $faultDetail];
    }
}
