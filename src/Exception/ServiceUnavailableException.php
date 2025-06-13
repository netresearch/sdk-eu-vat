<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Throwable;

/**
 * Exception for network and service availability issues
 * 
 * This exception is thrown when the EU VAT service is unavailable due to network issues,
 * service downtime, or internal server errors. It includes the TEDB-400 error code which
 * indicates internal application errors on the EU VAT service side.
 * 
 * @example Network timeout:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (ServiceUnavailableException $e) {
 *     // Implement retry logic
 *     $logger->warning('EU VAT service unavailable, retrying...');
 *     sleep(5);
 *     $response = $client->retrieveVatRates($request);
 * }
 * ```
 * 
 * @example Handling specific TEDB-400 error:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (ServiceUnavailableException $e) {
 *     if ($e->getErrorCode() === 'TEDB-400') {
 *         // Log the incident and notify operations about EU VAT service internal error
 *         $logger->critical('EU VAT service internal error', [
 *             'error' => $e->getMessage(),
 *             'request' => $request
 *         ]);
 *     } else {
 *         // Handle other service unavailability issues (e.g., network timeout)
 *     }
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class ServiceUnavailableException extends VatServiceException
{
    /**
     * @param string $message Error message
     * @param string|null $errorCode Optional error code (e.g., 'TEDB-400')
     * @param Throwable|null $previous Previous exception if any
     */
    public function __construct(
        string $message = "",
        private readonly ?string $errorCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the specific error code if available
     * 
     * @return string|null The error code (e.g., 'TEDB-400') or null if not applicable
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}