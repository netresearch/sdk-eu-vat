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
 * Factory for creating EU VAT Retrieval Service clients
 *
 * This factory provides convenient methods to create pre-configured SOAP clients
 * for production and sandbox environments. It abstracts the complex configuration
 * setup and provides sensible defaults for common scenarios.
 *
 * Supported environments:
 * - Production: Live EU VAT service endpoint with production-ready settings
 * - Sandbox: Test endpoint for development and testing
 *
 * @example Basic production usage:
 * ```php
 * $client = VatRetrievalClientFactory::create();
 * $response = $client->retrieveVatRates(new VatRatesRequest(['DE', 'FR'], new DateTime()));
 * ```
 *
 * @example Sandbox/testing:
 * ```php
 * $logger = new ConsoleLogger();
 * $client = VatRetrievalClientFactory::createSandbox($logger);
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
     * @param LoggerInterface|null     $logger Optional logger (defaults to NullLogger)
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
        $configuration = $config ?? self::createDefaultProductionConfig($logger);

        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client configured for sandbox/testing environments
     *
     * This creates a client configured for sandbox/testing with:
     * - Test endpoint (sandbox environment)
     * - Debug mode enabled for detailed logging
     * - Shorter timeout for faster failure detection
     * - Enhanced logging for debugging
     *
     * @param LoggerInterface|null $logger Optional logger for debug output (recommended for testing)
     *
     * @return VatRetrievalClientInterface Configured client for sandbox/testing
     *
     * @throws ConfigurationException If the sandbox configuration is invalid
     *
     * @example Sandbox with detailed logging:
     * ```php
     * $logger = new \Monolog\Logger('eu-vat-sandbox');
     * $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));
     *
     * $client = VatRetrievalClientFactory::createSandbox($logger);
     *
     * // This will log detailed SOAP request/response information
     * $response = $client->retrieveVatRates($request);
     * ```
     */
    public static function createSandbox(
        ?LoggerInterface $logger = null
    ): VatRetrievalClientInterface {
        $logger ??= new NullLogger();

        $configuration = ClientConfiguration::test($logger)
            ->withEndpoint(ClientConfiguration::ENDPOINT_TEST)
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
     * @param TelemetryInterface       $telemetry Telemetry implementation for monitoring
     * @param ClientConfiguration|null $config    Optional custom configuration
     * @param LoggerInterface|null     $logger    Optional logger
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
        $configuration = $config ?? self::createDefaultProductionConfig($logger);
        $configuration = $configuration->withTelemetry($telemetry);

        return new SoapVatRetrievalClient($configuration);
    }

    /**
     * Create a client with custom event subscribers for advanced integrations
     *
     * This allows for advanced configurations with custom event handling,
     * useful for enterprise integrations that need custom request/response processing.
     *
     * @param array<EventSubscriberInterface> $eventSubscribers Custom event subscribers
     * @param ClientConfiguration|null        $config           Optional base configuration
     * @param LoggerInterface|null            $logger           Optional logger
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
        $configuration = $config ?? self::createDefaultProductionConfig($logger);

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
     * Create default production configuration
     *
     * Provides consistent production configuration across all factory methods.
     * This ensures all production clients have the same baseline settings.
     *
     * @param LoggerInterface|null $logger Optional logger (defaults to NullLogger)
     * @return ClientConfiguration Production configuration with sensible defaults
     */
    private static function createDefaultProductionConfig(?LoggerInterface $logger = null): ClientConfiguration
    {
        $logger ??= new NullLogger();

        return ClientConfiguration::production($logger)
            ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
            ->withTimeout(self::DEFAULT_TIMEOUT);
    }
}
