<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

/**
 * Exception for DTO validation failures
 * 
 * This exception is thrown when data transfer objects (DTOs) receive invalid data
 * during construction or when validation methods detect invalid input. Common cases include:
 * - Invalid country codes (not 2 characters, not uppercase)
 * - Empty member states array
 * - Invalid date ranges
 * - Out-of-bounds numeric values
 * 
 * @example Invalid country code format:
 * ```php
 * try {
 *     $request = new VatRatesRequest(['de'], new DateTime()); // lowercase
 * } catch (ValidationException $e) {
 *     echo "Validation error: " . $e->getMessage();
 *     // Output: Validation error: Invalid member state code provided: de
 * }
 * ```
 * 
 * @example Empty member states:
 * ```php
 * try {
 *     $request = new VatRatesRequest([], new DateTime());
 * } catch (ValidationException $e) {
 *     echo "Validation error: " . $e->getMessage();
 *     // Output: Validation error: Member states array cannot be empty.
 * }
 * ```
 * 
 * @example Future date validation:
 * ```php
 * try {
 *     $futureDate = new DateTime('+10 years');
 *     $request = new VatRatesRequest(['DE'], $futureDate);
 * } catch (ValidationException $e) {
 *     echo "Validation error: " . $e->getMessage();
 *     // Output: Validation error: Date cannot be more than 5 years in the future
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class ValidationException extends VatServiceException
{
}