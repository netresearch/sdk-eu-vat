<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

/**
 * Exception for unexpected API responses
 *
 * This exception is thrown when the EU VAT service returns a response that doesn't match
 * the expected schema or contains unexpected data structures. This can happen when:
 * - The API returns a different response format than documented
 * - Required fields are missing from the response
 * - Response contains unexpected data types
 * - The service API has changed without notice
 *
 * @example Missing required field:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (UnexpectedResponseException $e) {
 *     echo "Unexpected response: " . $e->getMessage();
 *     // Output: Unexpected response: Missing required field 'vatRate' in response
 * }
 * ```
 *
 * @example Invalid response structure:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (UnexpectedResponseException $e) {
 *     if (str_contains($e->getMessage(), 'schema')) {
 *         // Handle schema mismatch
 *         $logger->critical('EU VAT API schema has changed', [
 *             'error' => $e->getMessage()
 *         ]);
 *         // Notify development team about API changes
 *     }
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class UnexpectedResponseException extends VatServiceException
{
}
