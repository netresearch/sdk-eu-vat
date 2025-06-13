<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

use Exception;

/**
 * Base exception for all EU VAT SDK errors
 * 
 * This abstract class serves as the foundation for all exceptions thrown by the EU VAT SDK.
 * It allows consumers to catch all SDK-specific exceptions with a single catch block while
 * still providing specific exception types for more granular error handling.
 * 
 * @example Catching all SDK exceptions:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (VatServiceException $e) {
 *     // Handle any SDK-specific error
 *     $logger->error('EU VAT SDK error: ' . $e->getMessage());
 * }
 * ```
 * 
 * @example Catching specific exceptions:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 * } catch (ValidationException $e) {
 *     // Handle validation errors
 * } catch (ServiceUnavailableException $e) {
 *     // Handle service availability issues
 * } catch (VatServiceException $e) {
 *     // Handle any other SDK errors
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
abstract class VatServiceException extends Exception
{
}