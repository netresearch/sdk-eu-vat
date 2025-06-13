<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

/**
 * Exception for SDK configuration errors
 * 
 * This exception is thrown when the SDK is incorrectly configured, such as:
 * - Invalid WSDL file path
 * - Malformed endpoint URLs
 * - Missing required configuration parameters
 * - Invalid SOAP options
 * 
 * @example Invalid WSDL path:
 * ```php
 * try {
 *     $config = new ClientConfiguration(
 *         wsdlPath: '/non/existent/path.wsdl'
 *     );
 *     $client = new SoapVatRetrievalClient($config);
 * } catch (ConfigurationException $e) {
 *     echo "Configuration error: " . $e->getMessage();
 *     // Output: Configuration error: WSDL file not found: /non/existent/path.wsdl
 * }
 * ```
 * 
 * @example Invalid endpoint URL:
 * ```php
 * try {
 *     $config = new ClientConfiguration(
 *         endpoint: 'not-a-valid-url'
 *     );
 * } catch (ConfigurationException $e) {
 *     echo "Configuration error: " . $e->getMessage();
 *     // Output: Configuration error: Invalid endpoint URL: not-a-valid-url
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class ConfigurationException extends VatServiceException
{
}