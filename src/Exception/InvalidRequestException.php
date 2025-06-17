<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Throwable;

/**
 * Exception for client-side validation errors and invalid API requests
 *
 * This exception is thrown when the request data is invalid according to the EU VAT service
 * specifications. It covers validation errors that are caught either client-side or returned
 * by the service with specific error codes.
 *
 * Known EU VAT service error codes mapped to this exception:
 * - TEDB-100: Invalid date format provided
 * - TEDB-101: Invalid country code provided
 * - TEDB-102: Empty member states array provided
 *
 * @example Invalid country code:
 * ```php
 * try {
 *     $request = new VatRatesRequest(['XX'], new DateTime());
 * } catch (InvalidRequestException $e) {
 *     echo "Invalid request: " . $e->getMessage();
 *     // Output: Invalid request: Invalid country code provided: XX
 * }
 * ```
 *
 * @example Handling specific TEDB codes:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (InvalidRequestException $e) {
 *     if ($e->getErrorCode() === 'TEDB-100') {
 *         // Handle invalid date format
 *     } elseif ($e->getErrorCode() === 'TEDB-101') {
 *         // Handle invalid country code
 *     } else {
 *         // Handle other invalid request errors
 *     }
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class InvalidRequestException extends VatServiceException
{
    /**
     * @param string         $message   Error message.
     * @param string|null    $errorCode Optional error code (e.g., 'TEDB-100', 'TEDB-101').
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
     * @return string|null The error code (e.g., 'TEDB-100') or null if not applicable
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
