<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

/**
 * Exception for data parsing errors
 *
 * This exception is thrown when the SDK fails to parse data, including:
 * - SOAP response parsing errors
 * - BigDecimal conversion failures for VAT rates
 * - Date/time parsing errors
 * - XML structure parsing failures
 *
 * @example BigDecimal parsing error:
 * ```php
 * try {
 *     $rate = new VatRate('STANDARD', 'invalid-number');
 * } catch (ParseException $e) {
 *     echo "Parse error: " . $e->getMessage();
 *     // Output: Parse error: Failed to parse decimal value: invalid-number
 * }
 * ```
 *
 * @example SOAP response parsing error:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (ParseException $e) {
 *     if (str_contains($e->getMessage(), 'XML')) {
 *         // Handle XML parsing errors
 *         $logger->error('Failed to parse SOAP response', [
 *             'error' => $e->getMessage()
 *         ]);
 *     }
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class ParseException extends VatServiceException
{
}
