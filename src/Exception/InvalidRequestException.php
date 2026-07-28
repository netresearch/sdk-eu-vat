<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Throwable;

/**
 * Exception for client-side validation errors and invalid API requests
 *
 * This exception is thrown when the request data is invalid according to the EU VAT service
 * specifications. It covers validation errors that are caught either client-side or reported
 * by the service.
 *
 * Service-side, this exception maps to SOAP faults the service attributes to the caller, i.e.
 * a faultcode whose local part is `Client` (SOAP 1.1) or `Sender` (SOAP 1.2). The TEDB error
 * identifier travels in the faultstring, for example `TEDB-ERR-2 - Request is not valid`, and
 * is exposed via getErrorCode(). The fault detail carries the individual service errors, e.g.
 * code `00002` with description `The Member State "XX" does not exist.`; those descriptions are
 * appended to the exception message.
 *
 * @example Invalid country code:
 * ```php
 * try {
 *     $request = new VatRatesRequest(['XX'], new DateTime());
 *     $response = $client->retrieveVatRates($request);
 * } catch (InvalidRequestException $e) {
 *     echo "Invalid request: " . $e->getMessage();
 *     // Output: Invalid request: Invalid request rejected by the EU VAT service
 *     //         (TEDB-ERR-2): TEDB-ERR-2 - Request is not valid
 *     //         ([00002] The Member State "XX" does not exist.)
 * }
 * ```
 *
 * @example Reacting to the TEDB identifier:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (InvalidRequestException $e) {
 *     if ($e->getErrorCode() === 'TEDB-ERR-2') {
 *         // The service rejected the request payload - fix the input and retry
 *     } else {
 *         // No TEDB identifier in the faultstring, or a locally raised validation error
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
     * @param string|null    $errorCode Optional error code. For service-side faults this is the
     *                                  TEDB identifier from the faultstring (e.g., 'TEDB-ERR-2'),
     *                                  falling back to the raw SOAP fault code.
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
     * @return string|null The error code (e.g., 'TEDB-ERR-2') or null if not applicable
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
