<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\ParseException;
use Netresearch\EuVatSdk\Exception\UnexpectedResponseException;
use Netresearch\EuVatSdk\EventListener\FaultEventListener;
use Netresearch\EuVatSdk\EventListener\RequestEventListener;
use Netresearch\EuVatSdk\EventListener\ResponseEventListener;
use Netresearch\EuVatSdk\TypeConverter\DateTimeTypeConverter;
use Netresearch\EuVatSdk\TypeConverter\BigDecimalTypeConverter;
use Soap\Engine\Engine;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMapCollection;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMap;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterCollection;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Transport\ExtSoapClientTransport;
use Soap\ExtSoapEngine\Exception\RequestException;
use Soap\Engine\Event\FaultEvent;
use Soap\Engine\Event\RequestEvent;
use Soap\Engine\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * SOAP client implementation for EU VAT Retrieval Service
 * 
 * This client provides a complete implementation of the VatRetrievalClientInterface
 * using the php-soap/ext-soap-engine library. It integrates all SDK components:
 * - DTOs for type-safe request/response handling
 * - Custom exceptions for domain-specific error handling
 * - TypeConverters for automatic data type conversion
 * - Event listeners for logging and fault handling
 * - Middleware support for cross-cutting concerns
 * 
 * The client automatically handles:
 * - WSDL parsing and caching
 * - SOAP fault mapping to domain exceptions
 * - Request/response logging (when debug mode is enabled)
 * - Type conversion between XML and PHP objects
 * - Connection timeouts and transport errors
 * 
 * @example Basic usage:
 * ```php
 * $config = ClientConfiguration::production($logger);
 * $client = new SoapVatRetrievalClient($config);
 * 
 * $request = new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01'));
 * $response = $client->retrieveVatRates($request);
 * ```
 * 
 * @package Netresearch\EuVatSdk\Client
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class SoapVatRetrievalClient implements VatRetrievalClientInterface
{
    /**
     * Default path to local WSDL file
     */
    private const LOCAL_WSDL_PATH = __DIR__ . '/../../resources/VatRetrievalService.wsdl';
    
    /**
     * SOAP engine instance for making requests
     */
    private Engine $engine;
    
    /**
     * Event dispatcher for manual event handling (v1.7.0 compatibility)
     */
    private ?EventDispatcher $eventDispatcher = null;
    
    /**
     * Create SOAP client with configuration
     * 
     * @param ClientConfiguration $config Client configuration including endpoint, timeouts, etc.
     * @param Engine|null $engine Optional pre-configured engine (for testing)
     * @throws ConfigurationException If client cannot be initialized
     */
    public function __construct(
        private ClientConfiguration $config,
        ?Engine $engine = null
    ) {
        $this->engine = $engine ?? $this->initializeEngine();
    }
    
    /**
     * Retrieve VAT rates for specified member states
     * 
     * This method makes a SOAP request to the EU VAT service and returns
     * the structured response as DTOs. All SOAP faults are automatically
     * mapped to domain exceptions through the event system.
     * 
     * @param VatRatesRequest $request Request containing member states and date
     * @return VatRatesResponse Structured response with VAT rate data
     * @throws InvalidRequestException For client-side validation errors (TEDB-100, 101, 102)
     * @throws ServiceUnavailableException For server-side errors (TEDB-400) or network issues
     * @throws ConfigurationException For WSDL or configuration errors
     * @throws VatServiceException For any other service-related errors
     * 
     * @example Making a request:
     * ```php
     * $request = new VatRatesRequest(
     *     memberStates: ['DE', 'FR', 'IT'],
     *     situationOn: new DateTime('2024-01-01')
     * );
     * 
     * try {
     *     $response = $client->retrieveVatRates($request);
     *     foreach ($response->getResults() as $result) {
     *         echo "{$result->getMemberState()}: {$result->getRate()->getValue()}%\n";
     *     }
     * } catch (InvalidRequestException $e) {
     *     // Handle client-side validation errors
     * } catch (ServiceUnavailableException $e) {
     *     // Handle service availability issues
     * }
     * ```
     */
    public function retrieveVatRates(VatRatesRequest $request): VatRatesResponse
    {
        try {
            // The engine automatically converts the DTO to SOAP request structure
            // and maps the response back to the VatRatesResponse DTO
            return $this->engine->request('retrieveVatRates', [$request]);
            
        } catch (\SoapFault $fault) {
            // This block should be unreachable if the FaultEventListener is working.
            // If it's reached, it indicates a failure in the event system itself.
            if ($this->config->logger) {
                $this->config->logger->critical(
                    'Unhandled SoapFault reached the client. The FaultEventListener may be misconfigured or has failed.',
                    ['fault_code' => $fault->faultcode, 'fault_message' => $fault->getMessage()]
                );
            }
            
            throw new SoapFaultException(
                "Unhandled SOAP fault: {$fault->getMessage()}",
                $fault->faultcode ?? 'UNKNOWN',
                $fault->faultstring ?? 'No fault string provided',
                $fault
            );
        } catch (RequestException $e) {
            throw new ServiceUnavailableException(
                'Network error occurred while connecting to EU VAT service: ' . $e->getMessage(),
                null, // errorCode should be null for network errors
                $e
            );
        } catch (\Throwable $e) {
            // Catch any other unexpected errors and wrap them for a consistent API.
            throw new UnexpectedResponseException(
                'An unexpected error occurred during the SOAP request: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Initialize the SOAP engine with all required components
     * 
     * This method sets up:
     * - ClassMap for DTO mapping
     * - TypeConverters for data type conversion
     * - Event listeners for logging and fault handling
     * - SOAP options and configuration
     * 
     * @return Engine Configured SOAP engine instance
     * @throws ConfigurationException If engine initialization fails
     */
    private function initializeEngine(): Engine
    {
        try {
            // 1. Define the ClassMap to map WSDL types to PHP DTOs
            // Note: These type names should match the WSDL complex type definitions
            $classMap = new ClassMapCollection(
                new ClassMap('retrieveVatRates', VatRatesRequest::class),
                new ClassMap('retrieveVatRatesResponse', VatRatesResponse::class),
                new ClassMap('vatRateResult', VatRateResult::class),
                new ClassMap('vatRate', VatRate::class)
            );

            // 2. Define TypeConverters for custom data types
            $typeConverters = new TypeConverterCollection([
                new DateTimeTypeConverter(), // Converts xsd:date to DateTimeImmutable
                new BigDecimalTypeConverter(), // Converts xsd:decimal to Brick\Math\BigDecimal
            ]);

            // 3. Create ExtSoapOptions with the ClassMap and TypeConverters
            $wsdlPath = $this->config->wsdlPath ?? self::LOCAL_WSDL_PATH;
            
            if (!file_exists($wsdlPath)) {
                throw new ConfigurationException("WSDL file not found at path: {$wsdlPath}");
            }
            
            // Pass classmap and typemap directly in the options array for v1.7.0 API
            $options = ExtSoapOptions::defaults($wsdlPath, [
                'location' => $this->config->endpoint,
                'connection_timeout' => $this->config->timeout,
                'classmap' => $classMap,
                'typemap' => $typeConverters,
                ...$this->config->soapOptions
            ]);

            // 4. Create EventDispatcher and register listeners
            $dispatcher = new EventDispatcher();
            
            // Register fault listener for error handling
            $faultListener = new FaultEventListener($this->config->logger);
            $dispatcher->addListener(FaultEvent::class, [$faultListener, 'handleSoapFault']);
            
            // Register debug listeners if debug mode is enabled
            if ($this->config->debug) {
                $requestListener = new RequestEventListener($this->config->logger, $this->config->debug);
                $responseListener = new ResponseEventListener($this->config->logger, $this->config->debug);
                
                $dispatcher->addListener(RequestEvent::class, [$requestListener, 'handleRequestEvent']);
                $dispatcher->addListener(ResponseEvent::class, [$responseListener, 'handleResponseEvent']);
            }

            // 5. Create the Engine
            $driver = ExtSoapDriver::createFromOptions($options);
            $transport = new ExtSoapClientTransport($driver->getClient());
            
            // Store dispatcher for manual event handling (v1.7.0 compatibility)
            $this->eventDispatcher = $dispatcher;
            
            return new SimpleEngine($driver, $transport);
            
        } catch (\Throwable $e) {
            throw new ConfigurationException(
                'Failed to initialize SOAP engine: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Get the current client configuration
     * 
     * @return ClientConfiguration Current configuration instance
     */
    public function getConfiguration(): ClientConfiguration
    {
        return $this->config;
    }
    
    /**
     * Get the SOAP engine instance (for testing/debugging)
     * 
     * @internal This method is intended for testing and debugging only
     * @return Engine Current engine instance
     */
    public function getEngine(): Engine
    {
        return $this->engine;
    }
}