<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\UnexpectedResponseException;
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
use Netresearch\EuVatSdk\EventListener\FaultEventListener;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;

/**
 * SOAP client implementation for EU VAT Retrieval Service
 *
 * This client provides a complete implementation of the VatRetrievalClientInterface
 * using the php-soap/ext-soap-engine library. It integrates all SDK components:
 * - DTOs for type-safe request/response handling
 * - Custom exceptions for domain-specific error handling
 * - TypeConverters for automatic data type conversion
 * - Direct SOAP fault handling and logging
 * - Telemetry recording for both successful and failed operations
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
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
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
    private const string LOCAL_WSDL_PATH = __DIR__ . '/../../resources/VatRetrievalService.wsdl';

    /**
     * Remote WSDL URL for fallback
     */
    private const string REMOTE_WSDL_URL = 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl';

    /**
     * SOAP engine instance for making requests
     */
    private readonly Engine $engine;

    /**
     * PSR-3 logger instance
     */
    private readonly LoggerInterface $logger;

    /**
     * Fault listener mapping SOAP faults to the documented domain exceptions
     */
    private readonly FaultEventListener $faultListener;

    /**
     * Telemetry sink recording operation timings and failures
     */
    private readonly TelemetryInterface $telemetry;

    /**
     * Operation name reported to telemetry
     */
    private const string OPERATION = 'retrieveVatRates';


    /**
     * Create SOAP client with configuration
     *
     * @param ClientConfiguration $config Client configuration including endpoint, timeouts, etc.
     * @param Engine|null         $engine Optional pre-configured engine (for testing)
     * @param VatRatesResponseConverter $responseConverter Response converter (injected for testing)
     * @throws ConfigurationException If client cannot be initialized
     */
    public function __construct(
        private readonly ClientConfiguration $config,
        ?Engine $engine = null,
        private readonly VatRatesResponseConverter $responseConverter = new VatRatesResponseConverter()
    ) {
        $this->logger = $this->config->logger;
        $this->telemetry = $this->config->telemetry;
        $this->faultListener = new FaultEventListener($this->logger);
        $this->engine = $engine ?? $this->initializeEngine();
    }

    /**
     * Retrieve VAT rates for specified member states
     *
     * This method makes a SOAP request to the EU VAT service and returns
     * the structured response as DTOs. All SOAP faults are automatically
     * mapped to domain exceptions in the catch block.
     *
     * The call is timed and reported to the configured TelemetryInterface:
     * recordRequest() on success, recordError() on failure. Telemetry failures
     * are logged and swallowed, so they never affect the outcome of the call.
     *
     * @param VatRatesRequest $request Request containing member states and date
     * @return VatRatesResponse Structured response with VAT rate data
     * @throws InvalidRequestException For faults the service attributes to the caller
     *         (faultcode `env:Client`), e.g. `TEDB-ERR-2 - Request is not valid`
     * @throws ServiceUnavailableException For faults the service attributes to itself
     *         (faultcode `env:Server`), network issues, and local ext-soap failures
     *         that never reached the service (bare `Client` faultcode, e.g. a non-XML
     *         response body from a proxy)
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
        $startTime = microtime(true);

        try {
            $response = $this->performRequest($request);
        } catch (\Throwable $e) {
            $this->recordErrorTelemetry($request, $e, microtime(true) - $startTime);

            throw $e;
        }

        $this->recordRequestTelemetry($request, $response, microtime(true) - $startTime);

        return $response;
    }

    /**
     * Execute the SOAP call and map every failure onto the documented exceptions
     *
     * @param VatRatesRequest $request Request containing member states and date
     * @return VatRatesResponse Structured response with VAT rate data
     * @throws VatServiceException For every failure mode documented on retrieveVatRates()
     */
    private function performRequest(VatRatesRequest $request): VatRatesResponse
    {
        try {
            $responseObject = $this->engine->request('retrieveVatRates', [$request]);

            // Convert stdClass response to strongly-typed DTO using dedicated converter
            // With ClassMap removed, SOAP engine should always return stdClass
            if (!$responseObject instanceof \stdClass) {
                throw new UnexpectedResponseException(
                    sprintf('Expected stdClass response from SOAP engine, got: %s', get_debug_type($responseObject))
                );
            }

            return $this->responseConverter->convert($responseObject);
        } catch (\SoapFault $fault) {
            // Delegate the documented SOAP fault mapping (always throws a domain exception)
            $this->faultListener->handleSoapFault($fault);

            // Unreachable fallback in case handleSoapFault ever returns without throwing
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
        } catch (VatServiceException $e) {
            // Let all SDK domain exceptions pass through unwrapped
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
     * Record a successful operation against the configured telemetry sink
     *
     * @param VatRatesRequest  $request  Request that was issued
     * @param VatRatesResponse $response Response that was returned
     * @param float            $duration Wall-clock duration in seconds, as TelemetryInterface documents
     */
    private function recordRequestTelemetry(
        VatRatesRequest $request,
        VatRatesResponse $response,
        float $duration
    ): void {
        $context = [
            'member_states' => $request->getMemberStates(),
            'situation_on' => $request->getSituationOn(),
            'result_count' => count($response->getResults()),
            'endpoint' => $this->config->endpoint,
        ];

        $this->recordTelemetry(
            static function (TelemetryInterface $telemetry) use ($duration, $context): void {
                $telemetry->recordRequest(self::OPERATION, $duration, $context);
            }
        );
    }

    /**
     * Record a failed operation against the configured telemetry sink
     *
     * The error type is the exception's short class name, as TelemetryInterface documents.
     *
     * @param VatRatesRequest $request   Request that was issued
     * @param \Throwable      $exception Failure that will be rethrown to the caller
     * @param float           $duration  Wall-clock time elapsed before the failure, in seconds
     */
    private function recordErrorTelemetry(VatRatesRequest $request, \Throwable $exception, float $duration): void
    {
        $context = [
            'member_states' => $request->getMemberStates(),
            'situation_on' => $request->getSituationOn(),
            'error_message' => $exception->getMessage(),
            'endpoint' => $this->config->endpoint,
            'duration' => $duration,
        ];

        if ($exception instanceof InvalidRequestException || $exception instanceof ServiceUnavailableException) {
            $context['error_code'] = $exception->getErrorCode();
        }

        $errorType = $exception::class;
        $shortName = strrchr($errorType, '\\');
        if ($shortName !== false) {
            $errorType = substr($shortName, 1);
        }

        $this->recordTelemetry(
            static function (TelemetryInterface $telemetry) use ($errorType, $context): void {
                $telemetry->recordError(self::OPERATION, $errorType, $context);
            }
        );
    }

    /**
     * Invoke the telemetry sink without letting its failures reach the caller
     *
     * Telemetry is an observability side channel: a broken metrics backend must never
     * turn a successful VAT lookup into an error, nor mask the exception a failed one
     * is about to throw. Failures are therefore swallowed and logged at warning level.
     *
     * @param callable(TelemetryInterface): void $record Recording call to perform
     */
    private function recordTelemetry(callable $record): void
    {
        try {
            $record($this->telemetry);
        } catch (\Throwable $e) {
            $this->logger->warning('Telemetry recording failed and was ignored', [
                'telemetry_class' => $this->telemetry::class,
                'error' => $e->getMessage(),
            ]);
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
     * Performs basic validation to ensure the WSDL file is accessible and appears
     * to be a valid WSDL file. The SOAP engine will perform detailed validation
     * upon loading.
     *
     * @param string $wsdlPath Path to WSDL file to validate
     * @return boolean True if WSDL file appears valid
     */
    private function validateWsdlFile(string $wsdlPath): bool
    {
        if (!file_exists($wsdlPath) || !is_readable($wsdlPath)) {
            $this->logger->warning('Local WSDL file is not accessible', [
                'wsdl_path' => $wsdlPath
            ]);
            return false;
        }

        try {
            $content = file_get_contents($wsdlPath);
            if ($content === false || trim($content) === '') {
                $this->logger->warning('Local WSDL file is empty or unreadable', [
                    'wsdl_path' => $wsdlPath
                ]);
                return false;
            }

            // Basic check for WSDL content - the SOAP engine will do thorough validation
            if (!str_contains($content, 'wsdl:definitions')) {
                $this->logger->warning('Local WSDL file appears to be invalid or not a WSDL file', [
                    'wsdl_path' => $wsdlPath
                ]);
                return false;
            }

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
                new BigDecimalTypeConverter(), // Converts xsd:double to Brick\Math\BigDecimal
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
