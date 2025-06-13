<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Throwable;

/**
 * Exception for SOAP fault responses from the EU VAT service
 * 
 * This exception is thrown when the EU VAT SOAP service returns a fault response.
 * It preserves the original SOAP fault code and fault string for detailed error analysis.
 * 
 * @example SOAP fault handling:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (SoapFaultException $e) {
 *     echo "SOAP Fault Code: " . $e->getFaultCode() . "\n";
 *     echo "SOAP Fault String: " . $e->getFaultString() . "\n";
 *     
 *     // Check for specific fault codes
 *     if ($e->getFaultCode() === 'SOAP-ENV:Server') {
 *         // Handle server-side errors
 *     }
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class SoapFaultException extends VatServiceException
{
    /**
     * @param string $message Human-readable error message
     * @param string $faultCode SOAP fault code (e.g., 'SOAP-ENV:Client', 'SOAP-ENV:Server')
     * @param string $faultString Original fault string from SOAP response
     * @param Throwable|null $previous Previous exception if any
     */
    public function __construct(
        string $message,
        private readonly string $faultCode,
        private readonly string $faultString,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the SOAP fault code
     * 
     * @return string The fault code from the SOAP response (e.g., 'SOAP-ENV:Client')
     */
    public function getFaultCode(): string
    {
        return $this->faultCode;
    }

    /**
     * Get the SOAP fault string
     * 
     * @return string The original fault string from the SOAP response
     */
    public function getFaultString(): string
    {
        return $this->faultString;
    }
}