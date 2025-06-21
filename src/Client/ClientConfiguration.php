<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Telemetry\NullTelemetry;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use Netresearch\EuVatSdk\Middleware\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Immutable configuration object for VAT retrieval client
 *
 * This class provides a type-safe, immutable configuration container with
 * fluent interface for building client configurations. It follows the
 * builder pattern with factory methods for common scenarios.
 *
 * The configuration object is immutable - all modification methods return
 * new instances rather than modifying the existing instance, ensuring
 * thread safety and preventing accidental configuration changes.
 *
 * @example Basic production configuration:
 * ```php
 * $config = ClientConfiguration::production();
 * $client = new SoapVatRetrievalClient($config);
 * ```
 *
 * @example Custom configuration with fluent interface:
 * ```php
 * $config = ClientConfiguration::production()
 *     ->withTimeout(60)
 *     ->withDebug(true)
 *     ->withLogger($psrLogger)
 *     ->withTelemetry($telemetryImplementation);
 * ```
 *
 * @example Test environment configuration:
 * ```php
 * $config = ClientConfiguration::test($logger)
 *     ->withTimeout(120); // Longer timeout for test environment
 * ```
 *
 * @package Netresearch\EuVatSdk\Client
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class ClientConfiguration
{
    /**
     * EU VAT Retrieval Service production endpoint
     */
    public const ENDPOINT_PRODUCTION = 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService';

    /**
     * EU VAT Retrieval Service acceptance/test endpoint
     */
    public const ENDPOINT_TEST = 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService-ACC';

    /**
     * Default connection timeout in seconds
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Maximum reasonable timeout in seconds
     */
    public const MAX_TIMEOUT = 300;

    /**
     * Service endpoint URL for SOAP requests
     */
    public readonly string $endpoint;

    /**
     * SOAP client options array
     *
     * @var array<string, mixed>
     */
    public readonly array $soapOptions;

    /**
     * Connection timeout in seconds
     */
    public readonly int $timeout;

    /**
     * Local WSDL file path (null for remote WSDL)
     */
    public readonly ?string $wsdlPath;

    /**
     * Private constructor enforces use of factory methods
     *
     * @param string               $endpoint         Service endpoint URL
     * @param array<string, mixed> $soapOptions      SOAP client configuration options
     * @param integer              $timeout          Connection timeout in seconds
     * @param boolean              $debug            Enable debug mode
     * @param LoggerInterface      $logger           PSR-3 logger implementation
     * @param string|null          $wsdlPath         Local WSDL file path
     * @param TelemetryInterface   $telemetry        Telemetry implementation
     * @param array<object>        $eventSubscribers Event subscriber objects
     * @param array<MiddlewareInterface> $middleware Middleware objects
     *
     * @throws ConfigurationException If configuration values are invalid
     */
    private function __construct(
        string $endpoint,
        array $soapOptions,
        int $timeout,
        public readonly bool $debug,
        public readonly LoggerInterface $logger,
        ?string $wsdlPath,
        public readonly TelemetryInterface $telemetry,
        /**
         * Array of event subscriber objects for extension
         */
        public readonly array $eventSubscribers,
        /**
         * Array of middleware objects for request/response processing
         * @var array<MiddlewareInterface>
         */
        public readonly array $middleware
    ) {
        $this->validateConfiguration($endpoint, $timeout, $wsdlPath);

        $this->endpoint = $endpoint;
        $this->timeout = $timeout;
        $this->wsdlPath = $wsdlPath;

        // Merge SOAP options with defaults, ensuring timeout and debug are always current
        $defaultOptions = [
            'connection_timeout' => $timeout,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'soap_version' => SOAP_1_1,
            'trace' => $this->debug,
            'exceptions' => true,
        ];

        // User-provided options override defaults, but timeout and debug always reflect current values
        $this->soapOptions = array_merge($defaultOptions, $soapOptions, [
            'connection_timeout' => $timeout,
            'trace' => $this->debug,
        ]);
    }

    /**
     * Create production configuration with recommended defaults
     *
     * @param LoggerInterface|null $logger Optional PSR-3 logger (defaults to NullLogger)
     * @return self New configuration instance for production use
     *
     * @example Basic production setup:
     * ```php
     * $config = ClientConfiguration::production();
     * ```
     *
     * @example Production with logging:
     * ```php
     * $config = ClientConfiguration::production($monologLogger);
     * ```
     */
    public static function production(?LoggerInterface $logger = null): self
    {
        return new self(
            endpoint: self::ENDPOINT_PRODUCTION,
            soapOptions: [],
            timeout: self::DEFAULT_TIMEOUT,
            debug: false,
            logger: $logger ?? new NullLogger(),
            wsdlPath: null,
            telemetry: new NullTelemetry(),
            eventSubscribers: [],
            middleware: []
        );
    }

    /**
     * Create test/acceptance configuration with debug enabled
     *
     * @param LoggerInterface|null $logger Optional PSR-3 logger (defaults to NullLogger)
     * @return self New configuration instance for test environment
     *
     * @example Test environment setup:
     * ```php
     * $config = ClientConfiguration::test($testLogger);
     * ```
     */
    public static function test(?LoggerInterface $logger = null): self
    {
        return new self(
            endpoint: self::ENDPOINT_TEST,
            soapOptions: [],
            timeout: self::DEFAULT_TIMEOUT,
            debug: true, // Enable debug for test environment
            logger: $logger ?? new NullLogger(),
            wsdlPath: null,
            telemetry: new NullTelemetry(),
            eventSubscribers: [],
            middleware: []
        );
    }

    /**
     * Create new instance with custom endpoint
     *
     * @param string $endpoint Service endpoint URL
     * @return self New configuration instance with updated endpoint
     *
     * @example Set custom endpoint:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withEndpoint('https://custom.endpoint.com');
     * ```
     */
    public function withEndpoint(string $endpoint): self
    {
        return new self(
            $endpoint, // Updated value
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with modified timeout
     *
     * @param integer $seconds Connection timeout in seconds (1-300)
     * @return self New configuration instance with updated timeout
     *
     * @throws ConfigurationException If timeout is out of valid range
     *
     * @example Increase timeout for slow networks:
     * ```php
     * $config = ClientConfiguration::production()->withTimeout(60);
     * ```
     */
    public function withTimeout(int $seconds): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $seconds, // Updated value
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with modified debug mode
     *
     * @param boolean $enabled Enable or disable debug mode
     * @return self New configuration instance with updated debug setting
     *
     * @example Enable debug for troubleshooting:
     * ```php
     * $config = ClientConfiguration::production()->withDebug(true);
     * ```
     */
    public function withDebug(bool $enabled): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $enabled, // Updated value
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with modified logger
     *
     * @param LoggerInterface $logger PSR-3 compatible logger
     * @return self New configuration instance with updated logger
     *
     * @example Add custom logger:
     * ```php
     * $config = ClientConfiguration::production()->withLogger($monologLogger);
     * ```
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $logger, // Updated value
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with modified WSDL path
     *
     * @param string|null $path Local WSDL file path (null for remote WSDL)
     * @return self New configuration instance with updated WSDL path
     *
     * @throws ConfigurationException If WSDL file doesn't exist or isn't readable
     *
     * @example Use local WSDL file:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withWsdlPath(__DIR__ . '/resources/VatRetrievalService.wsdl');
     * ```
     */
    public function withWsdlPath(?string $path): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $this->logger,
            $path, // Updated value
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with modified telemetry implementation
     *
     * @param TelemetryInterface $telemetry Telemetry implementation for observability
     * @return self New configuration instance with updated telemetry
     *
     * @example Add custom telemetry:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withTelemetry($prometheusVatTelemetry);
     * ```
     */
    public function withTelemetry(TelemetryInterface $telemetry): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $telemetry, // Updated value
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with additional SOAP options
     *
     * @param array<string, mixed> $options SOAP client options to merge
     * @return self New configuration instance with merged SOAP options
     *
     * @example Add custom SOAP options:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withSoapOptions(['user_agent' => 'MyApp/1.0']);
     * ```
     */
    public function withSoapOptions(array $options): self
    {
        // Merge new options with existing ones, new options take precedence
        $mergedOptions = array_merge($this->soapOptions, $options);

        return new self(
            $this->endpoint,
            $mergedOptions, // Updated value
            $this->timeout,
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            $this->middleware
        );
    }

    /**
     * Create new instance with additional event subscriber
     *
     * @param object $subscriber Event subscriber object
     * @return self New configuration instance with added event subscriber
     *
     * @example Add custom event subscriber:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withEventSubscriber($customEventSubscriber);
     * ```
     */
    public function withEventSubscriber(object $subscriber): self
    {
        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            [...$this->eventSubscribers, $subscriber], // Updated value
            $this->middleware
        );
    }

    /**
     * Create new instance with additional middleware
     *
     * @param MiddlewareInterface|array<MiddlewareInterface> $middleware Middleware object or array
     * @return self New configuration instance with added middleware
     *
     * @example Add custom middleware:
     * ```php
     * $config = ClientConfiguration::production()
     *     ->withMiddleware($cachingMiddleware);
     * ```
     */
    public function withMiddleware(MiddlewareInterface|array $middleware): self
    {
        $middlewareArray = is_array($middleware) ? $middleware : [$middleware];

        return new self(
            $this->endpoint,
            $this->soapOptions,
            $this->timeout,
            $this->debug,
            $this->logger,
            $this->wsdlPath,
            $this->telemetry,
            $this->eventSubscribers,
            [...$this->middleware, ...$middlewareArray] // Updated value
        );
    }

    /**
     * Validate configuration parameters
     *
     * @param string      $endpoint Service endpoint URL
     * @param integer     $timeout  Connection timeout in seconds
     * @param string|null $wsdlPath Local WSDL file path
     *
     * @throws ConfigurationException If any parameter is invalid
     */
    private function validateConfiguration(string $endpoint, int $timeout, ?string $wsdlPath): void
    {
        // Validate endpoint URL
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new ConfigurationException(
                sprintf('Invalid endpoint URL: %s', $endpoint)
            );
        }

        // Validate timeout range
        if ($timeout < 1 || $timeout > self::MAX_TIMEOUT) {
            throw new ConfigurationException(
                sprintf(
                    'Timeout must be between 1 and %d seconds, got: %d',
                    self::MAX_TIMEOUT,
                    $timeout
                )
            );
        }

        // Validate WSDL file if provided
        if ($wsdlPath !== null) {
            if (!file_exists($wsdlPath)) {
                throw new ConfigurationException(
                    sprintf('WSDL file not found: %s', $wsdlPath)
                );
            }

            if (!is_readable($wsdlPath)) {
                throw new ConfigurationException(
                    sprintf('WSDL file is not readable: %s', $wsdlPath)
                );
            }
        }
    }
}
