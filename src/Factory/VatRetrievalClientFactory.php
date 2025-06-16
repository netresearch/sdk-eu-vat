<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Factory;

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Factory for creating EU VAT Retrieval Service clients with various configurations
 * 
 * This factory provides convenient methods to create pre-configured SOAP clients
 * for different environments and use cases. It abstracts the complex configuration
 * setup and provides sensible defaults for common scenarios.
 * 
 * @example Basic usage:
 * ```php
 * $client = VatRetrievalClientFactory::create();
 * $response = $client->retrieveVatRates(new VatRatesRequest(['DE', 'FR'], new DateTime()));
 * ```
 * 
 * @example Testing with custom configuration:
 * ```php
 * $logger = new ConsoleLogger();
 * $client = VatRetrievalClientFactory::createForTesting($logger);
 * $response = $client->retrieveVatRates($request);
 * ```
 * 
 * @example Production with telemetry:
 * ```php
 * $telemetry = new MyCustomTelemetry();
 * $client = VatRetrievalClientFactory::createWithTelemetry($telemetry);
 * ```
 * 
 * @package Netresearch\EuVatSdk\Factory
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class VatRetrievalClientFactory
{
    /**
     * Default connection timeout in seconds
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Create a production-ready client with default configuration
     * 
     * This creates a client configured for production use with:
     * - Production EU VAT service endpoint
     * - WSDL disk caching enabled
     * - 30-second timeout
     * - Minimal logging (NullLogger)
     * 
     * @param ClientConfiguration|null $config Optional custom configuration to override defaults
     * @param LoggerInterface|null $logger Optional logger (defaults to NullLogger)
     * 
     * @return VatRetrievalClientInterface Configured SOAP client ready for production use
     * 
     * @throws ConfigurationException If the configuration is invalid or WSDL cannot be loaded
     * 
     * @example Basic production client:
     * ```php
     * $client = VatRetrievalClientFactory::create();
     * 
     * try {
     *     $request = new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01'));
     *     $response = $client->retrieveVatRates($request);
     *     
     *     foreach ($response->getResults() as $result) {
     *         echo "Country: {$result->getMemberState()}, Rate: {$result->getVatRate()->getValue()}%\n";
     *     }
     * } catch (VatServiceException $e) {
     *     // Handle SDK-specific errors
     *     error_log("EU VAT API error: " . $e->getMessage());
     * }
     * ```
     */
    public static function create(
        ?ClientConfiguration $config = null,
        ?LoggerInterface $logger = null
    ): VatRetrievalClientInterface {
        $logger = $logger ?? new NullLogger();
        
        $configuration = $config ?? ClientConfiguration::production($logger)
            ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
            ->withTimeout(self::DEFAULT_TIMEOUT);
            
        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client configured for testing and development
     * 
     * This creates a client configured for development/testing with:
     * - Test endpoint (if available)
     * - Debug mode enabled for detailed logging
     * - Shorter timeout for faster failure detection
     * - Enhanced logging for debugging
     * 
     * @param LoggerInterface|null $logger Optional logger for debug output (recommended for testing)
     * @param bool $useTestEndpoint Whether to use the test endpoint (default: true)
     * 
     * @return VatRetrievalClientInterface Configured client for testing
     * 
     * @throws ConfigurationException If the test configuration is invalid
     * 
     * @example Testing with detailed logging:
     * ```php
     * $logger = new \Monolog\Logger('eu-vat-test');
     * $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));
     * 
     * $client = VatRetrievalClientFactory::createForTesting($logger);
     * 
     * // This will log detailed SOAP request/response information
     * $response = $client->retrieveVatRates($request);
     * ```
     */
    public static function createForTesting(
        ?LoggerInterface $logger = null,
        bool $useTestEndpoint = true
    ): VatRetrievalClientInterface {
        $logger = $logger ?? new NullLogger();
        
        $endpoint = $useTestEndpoint ? ClientConfiguration::ENDPOINT_TEST : ClientConfiguration::ENDPOINT_PRODUCTION;
        
        $configuration = ClientConfiguration::test($logger)
            ->withEndpoint($endpoint)
            ->withTimeout(15) // Shorter timeout for testing
            ->withDebug(true); // Enable debug logging
            
        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client with telemetry integration for production monitoring
     * 
     * This creates a production client with telemetry support for monitoring
     * and observability in production environments.
     * 
     * @param TelemetryInterface $telemetry Telemetry implementation for monitoring
     * @param ClientConfiguration|null $config Optional custom configuration
     * @param LoggerInterface|null $logger Optional logger
     * 
     * @return VatRetrievalClientInterface Configured client with telemetry
     * 
     * @throws ConfigurationException If the configuration is invalid
     * 
     * @example Production monitoring:
     * ```php
     * // Implement your custom telemetry
     * class MyTelemetry implements TelemetryInterface {
     *     public function recordRequest(string $operation, float $duration, array $context = []): void {
     *         // Send metrics to your monitoring system
     *         $this->metrics->timing("eu_vat.{$operation}.duration", $duration);
     *         $this->metrics->increment("eu_vat.{$operation}.requests");
     *     }
     *     
     *     public function recordError(string $operation, string $errorType, array $context = []): void {
     *         $this->metrics->increment("eu_vat.{$operation}.errors", ['type' => $errorType]);
     *     }
     * }
     * 
     * $telemetry = new MyTelemetry();
     * $client = VatRetrievalClientFactory::createWithTelemetry($telemetry);
     * ```
     */
    public static function createWithTelemetry(
        TelemetryInterface $telemetry,
        ?ClientConfiguration $config = null,
        ?LoggerInterface $logger = null
    ): VatRetrievalClientInterface {
        $logger = $logger ?? new NullLogger();
        
        $configuration = $config ?? ClientConfiguration::production($logger)
            ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
            ->withTimeout(self::DEFAULT_TIMEOUT);
            
        $configuration = $configuration->withTelemetry($telemetry);
        
        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client with custom event subscribers for advanced integrations
     * 
     * This allows for advanced configurations with custom event handling,
     * useful for enterprise integrations that need custom request/response processing.
     * 
     * @param EventSubscriberInterface[] $eventSubscribers Custom event subscribers
     * @param ClientConfiguration|null $config Optional base configuration
     * @param LoggerInterface|null $logger Optional logger
     * 
     * @return VatRetrievalClientInterface Configured client with custom event handling
     * 
     * @throws ConfigurationException If the configuration is invalid
     * 
     * @example Custom event handling:
     * ```php
     * class RequestMetricsSubscriber implements EventSubscriberInterface {
     *     public static function getSubscribedEvents(): array {
     *         return [RequestEvent::class => 'onRequest'];
     *     }
     *     
     *     public function onRequest(RequestEvent $event): void {
     *         // Custom request processing
     *         $this->metricsCollector->recordRequestStart();
     *     }
     * }
     * 
     * $subscribers = [new RequestMetricsSubscriber()];
     * $client = VatRetrievalClientFactory::createWithEventSubscribers($subscribers);
     * ```
     */
    public static function createWithEventSubscribers(
        array $eventSubscribers,
        ?ClientConfiguration $config = null,
        ?LoggerInterface $logger = null
    ): VatRetrievalClientInterface {
        $logger = $logger ?? new NullLogger();
        
        $configuration = $config ?? ClientConfiguration::production($logger)
            ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
            ->withTimeout(self::DEFAULT_TIMEOUT);
            
        foreach ($eventSubscribers as $subscriber) {
            if (!$subscriber instanceof EventSubscriberInterface) {
                throw new ConfigurationException(
                    'All event subscribers must implement EventSubscriberInterface'
                );
            }
            $configuration = $configuration->withEventSubscriber($subscriber);
        }
        
        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client with environment-based configuration
     * 
     * This method automatically selects appropriate settings based on the environment.
     * Useful for applications that deploy to multiple environments.
     * 
     * @param string $environment Environment name ('production', 'staging', 'development', 'testing')
     * @param LoggerInterface|null $logger Optional logger
     * @param array $options Additional options for environment-specific configuration
     * 
     * @return VatRetrievalClientInterface Environment-specific configured client
     * 
     * @throws ConfigurationException If the environment is not supported
     * 
     * @example Environment-based configuration:
     * ```php
     * // Automatically configures based on environment
     * $client = VatRetrievalClientFactory::createForEnvironment('production');
     * 
     * // With custom options
     * $client = VatRetrievalClientFactory::createForEnvironment('staging', $logger, [
     *     'timeout' => 45,
     *     'debug' => true
     * ]);
     * ```
     */
    public static function createForEnvironment(
        string $environment,
        ?LoggerInterface $logger = null,
        array $options = []
    ): VatRetrievalClientInterface {
        $logger = $logger ?? new NullLogger();
        
        $config = match (strtolower($environment)) {
            'production' => ClientConfiguration::production($logger)
                ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
                ->withTimeout($options['timeout'] ?? self::DEFAULT_TIMEOUT)
                ->withDebug($options['debug'] ?? false),
                
            'staging' => ClientConfiguration::production($logger)
                ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION) // Use production endpoint for staging
                ->withTimeout($options['timeout'] ?? 45) // Longer timeout for staging
                ->withDebug($options['debug'] ?? true), // Enable debug in staging
                
            'development', 'dev' => ClientConfiguration::test($logger)
                ->withEndpoint($options['endpoint'] ?? ClientConfiguration::ENDPOINT_TEST)
                ->withTimeout($options['timeout'] ?? 15)
                ->withDebug(true),
                
            'testing', 'test' => ClientConfiguration::test($logger)
                ->withEndpoint($options['endpoint'] ?? ClientConfiguration::ENDPOINT_TEST)
                ->withTimeout($options['timeout'] ?? 10)
                ->withDebug(true),
                
            default => throw new ConfigurationException(
                "Unsupported environment: {$environment}. " .
                "Supported environments: production, staging, development, testing"
            )
        };
        
        return new SoapVatRetrievalClient($config);
    }
}