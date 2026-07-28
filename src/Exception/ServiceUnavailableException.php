<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Throwable;

/**
 * Exception for network and service availability issues
 *
 * This exception is thrown when the EU VAT service is unavailable due to network issues,
 * service downtime, or internal server errors.
 *
 * Two sources feed it:
 * - Transport failures (connection refused, timeout, DNS). These carry no error code, so
 *   getErrorCode() returns null.
 * - SOAP faults the service attributes to itself, i.e. a faultcode whose local part is
 *   `Server` (SOAP 1.1) or `Receiver` (SOAP 1.2). getErrorCode() then returns the TEDB
 *   identifier from the faultstring if one is present, otherwise the raw SOAP fault code
 *   (e.g. `env:Server`).
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
 * @example Separating transport failures from service-side faults:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (ServiceUnavailableException $e) {
 *     if ($e->getErrorCode() === null) {
 *         // Transport failure - safe to retry after a short backoff
 *         $logger->warning('EU VAT service unreachable', ['error' => $e->getMessage()]);
 *     } else {
 *         // The service reported an internal error; notify operations
 *         $logger->critical('EU VAT service internal error', [
 *             'code' => $e->getErrorCode(),
 *             'error' => $e->getMessage(),
 *         ]);
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
     * @param string         $message   Error message.
     * @param string|null    $errorCode Optional error code. Null for transport failures; for
     *                                  service-side faults the TEDB identifier from the
     *                                  faultstring, falling back to the raw SOAP fault code.
     * @param Throwable|null $previous  Previous exception if any.
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
     * @return string|null The error code (e.g., 'env:Server') or null for transport failures
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
