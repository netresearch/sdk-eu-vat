<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Client;

use DOMDocument;
use DOMXPath;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\UnexpectedResponseException;
use Netresearch\EuVatSdk\Exception\ConversionException;
use Netresearch\EuVatSdk\TypeConverter\DateTypeConverter;
use Netresearch\EuVatSdk\TypeConverter\BigDecimalTypeConverter;
use Netresearch\EuVatSdk\Converter\VatRatesResponseConverter;
use Soap\Engine\Engine;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterCollection;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Transport\ExtSoapClientTransport;
use Soap\ExtSoapEngine\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Netresearch\EuVatSdk\Engine\EventAwareEngine;
use Netresearch\EuVatSdk\Engine\MiddlewareEngine;

/**
 * SOAP client implementation for EU VAT Retrieval Service
 *
 * This client provides a complete implementation of the VatRetrievalClientInterface
 * using the php-soap/ext-soap-engine library. It integrates all SDK components:
 * - DTOs for type-safe request/response handling
 * - Custom exceptions for domain-specific error handling
 * - TypeConverters for automatic data type conversion
 * - Direct SOAP fault handling and logging
 *
 * The client automatically handles:
 * - WSDL parsing and caching
 * - SOAP fault mapping to domain exceptions
 * - Type conversion between XML and PHP objects
 * - Connection timeouts and transport errors
 *
 * @example Basic usage:
 * ```php
 * $config = ClientConfiguration::production($logger);
 * $client = new SoapVatRetrievalClient($config);
 * $request = new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01'));
 * $response = $client->retrieveVatRates($request);
 * ```
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * Note: High coupling is justified as this is a central integration point that orchestrates
 * SOAP engine, DTOs, exceptions, type converters, logging, and WSDL validation.
 * Future refactoring should extract concerns like DTO mapping, exception handling,
 * and telemetry into dedicated services with dependency injection.
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
     * Remote WSDL URL for fallback
     */
    private const REMOTE_WSDL_URL = 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl';

    /**
     * SOAP engine instance for making requests
     */
    private readonly Engine $engine;

    /**
     * PSR-3 logger instance
     */
    private readonly LoggerInterface $logger;


    /**
     * Create SOAP client with configuration
     *
     * @param ClientConfiguration $config Client configuration including endpoint, timeouts, etc.
     * @param Engine|null         $engine Optional pre-configured engine (for testing)
     * @throws ConfigurationException If client cannot be initialized
     */
    public function __construct(
        private readonly ClientConfiguration $config,
        ?Engine $engine = null
    ) {
        $this->logger = $this->config->logger ?? new NullLogger();
        $this->engine = $engine ?? $this->initializeEngine();
    }

    /**
     * Retrieve VAT rates for specified member states
     *
     * This method makes a SOAP request to the EU VAT service and returns
     * the structured response as DTOs. All SOAP faults are automatically
     * mapped to domain exceptions in the catch block.
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
            /** @var \stdClass $responseObject */
            $responseObject = $this->engine->request('retrieveVatRates', [$request]);

            // Convert stdClass response to strongly-typed DTO using dedicated converter
            // With ClassMap removed, SOAP engine should always return stdClass
            if (!$responseObject instanceof \stdClass) {
                throw new UnexpectedResponseException(
                    sprintf('Expected stdClass response from SOAP engine, got: %s', get_debug_type($responseObject))
                );
            }

            $converter = new VatRatesResponseConverter();
            return $converter->convert($responseObject);
        } catch (\SoapFault $fault) {
            // FaultEventListener should have already handled this, but as a fallback
            throw new SoapFaultException(
                $fault->getMessage(),
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
        } catch (ConversionException $e) {
            // Let ConversionException pass through - it's a domain exception
            throw $e;
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
     * Resolve WSDL path with fallback logic
     *
     * This method implements a fallback strategy for WSDL loading:
     * 1. Use configured WSDL path if specified and valid
     * 2. Use local bundled WSDL if available
     * 3. Fall back to remote WSDL URL
     *
     * @return string Valid WSDL path or URL
     * @throws ConfigurationException If no valid WSDL source can be found
     */
    private function resolveWsdlPath(): string
    {
        // 1. Try configured WSDL path first
        if ($this->config->wsdlPath !== null) {
            if (
                file_exists($this->config->wsdlPath)
                && is_file($this->config->wsdlPath)
                && is_readable($this->config->wsdlPath)
            ) {
                $this->logger->debug('Using configured WSDL path', [
                    'wsdl_path' => $this->config->wsdlPath
                ]);
                return $this->config->wsdlPath;
            }

            // Log warning about invalid configured path but continue with fallback
            $this->logger->warning('Configured WSDL path is invalid, using fallback', [
                'configured_path' => $this->config->wsdlPath
            ]);
        }

        // 2. Try local bundled WSDL
        // Validate WSDL file integrity
        if (
            file_exists(self::LOCAL_WSDL_PATH)
            && is_file(self::LOCAL_WSDL_PATH)
            && is_readable(self::LOCAL_WSDL_PATH)
            && $this->validateWsdlFile(self::LOCAL_WSDL_PATH)
        ) {
            $this->logger->debug('Using local bundled WSDL', [
                'wsdl_path' => self::LOCAL_WSDL_PATH
            ]);
            return self::LOCAL_WSDL_PATH;
        }

        // 3. Fall back to remote WSDL
        $this->logger->info('Using remote WSDL fallback', [
            'wsdl_url' => self::REMOTE_WSDL_URL
        ]);

        return self::REMOTE_WSDL_URL;
    }

    /**
     * Validate WSDL file integrity
     *
     * Performs basic validation to ensure the WSDL file is not corrupted
     * and contains the expected service definition.
     *
     * @param string $wsdlPath Path to WSDL file to validate
     * @return boolean True if WSDL file appears valid
     */
    private function validateWsdlFile(string $wsdlPath): bool
    {
        try {
            $content = file_get_contents($wsdlPath);
            if ($content === false) {
                return false;
            }

            // Load and validate XML structure with enhanced XPath validation
            $previousSetting = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $isValid = $dom->loadXML($content);

            if (!$isValid) {
                $this->logger->warning('WSDL file is not well-formed XML', [
                    'wsdl_path' => $wsdlPath
                ]);
                libxml_use_internal_errors($previousSetting);
                return false;
            }

            // Enhanced validation using XPath to check for required WSDL elements
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('wsdl', 'http://schemas.xmlsoap.org/wsdl/');
            $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/wsdl/soap/');

            // Check for required WSDL structure
            $requiredElements = [
                '//wsdl:definitions' => 'WSDL definitions element',
                '//wsdl:service[@name="vatRetrievalServiceService"]' => 'vatRetrievalServiceService service definition',
                '//wsdl:portType[@name="vatRetrievalService"]' => 'vatRetrievalService interface',
                '//wsdl:operation[@name="retrieveVatRates"]' => 'retrieveVatRates operation'
            ];

            foreach ($requiredElements as $xpathQuery => $description) {
                $nodes = $xpath->query($xpathQuery);
                if (!$nodes || $nodes->length === 0) {
                    $this->logger->warning('WSDL validation failed: missing required element', [
                        'wsdl_path' => $wsdlPath,
                        'missing_element' => $description,
                        'xpath_query' => $xpathQuery
                    ]);
                    libxml_use_internal_errors($previousSetting);
                    return false;
                }
            }

            libxml_use_internal_errors($previousSetting);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Error validating WSDL file', [
                'wsdl_path' => $wsdlPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Initialize the SOAP engine with all required components
     *
     * This method sets up:
     * - ClassMap for DTO mapping
     * - TypeConverters for data type conversion
     * - SOAP options and configuration
     *
     * @return Engine Configured SOAP engine instance
     * @throws ConfigurationException If engine initialization fails
     */
    private function initializeEngine(): Engine
    {
        try {
            // ClassMap has been removed - incompatible with constructor-based immutable DTOs
            // All object hydration is now handled by VatRatesResponseConverter for clean separation

            // 2. Define TypeConverters for custom data types
            $typeConverters = new TypeConverterCollection([
                new DateTypeConverter(), // Converts xsd:date to DateTimeImmutable
                new BigDecimalTypeConverter(), // Converts xsd:decimal to Brick\Math\BigDecimal
            ]);

            // 3. Create ExtSoapOptions with basic configuration
            $wsdlPath = $this->resolveWsdlPath();

            $options = ExtSoapOptions::defaults($wsdlPath, [
                'location' => $this->config->endpoint,
                'connection_timeout' => $this->config->timeout,
                ...$this->config->soapOptions
            ])
            ->withTypeMap($typeConverters);

            // 4. Create the base engine
            $driver = ExtSoapDriver::createFromOptions($options);
            $transport = new ExtSoapClientTransport($driver->getClient());
            $baseEngine = new SimpleEngine($driver, $transport);

            // 5. Add event dispatcher support if event subscribers are configured
            $engine = $baseEngine;
            if ($this->config->eventSubscribers !== []) {
                $dispatcher = new EventDispatcher();

                // Add configured event subscribers
                foreach ($this->config->eventSubscribers as $subscriber) {
                    if ($subscriber instanceof EventSubscriberInterface) {
                        $dispatcher->addSubscriber($subscriber);
                        continue;
                    }

                    // Log warning for invalid event listeners
                    $this->logger->warning(
                        'Provided event listener does not implement EventSubscriberInterface and was ignored.',
                        ['listener_class' => $subscriber::class]
                    );
                }

                // Wrap engine with event dispatcher
                $engine = new EventAwareEngine($driver, $transport, $dispatcher);
            }

            // 6. Add middleware support if middleware is configured
            if ($this->config->middleware !== []) {
                return new MiddlewareEngine($engine, $this->config->middleware);
            }

            return $engine;
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
