<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Factory;

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test VatRetrievalClientFactory
 */
class VatRetrievalClientFactoryTest extends TestCase
{
    private LoggerInterface $logger;
    private TelemetryInterface $telemetry;
    private EventSubscriberInterface $eventSubscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->telemetry = $this->createMock(TelemetryInterface::class);
        $this->eventSubscriber = $this->createMock(EventSubscriberInterface::class);
    }

    public function testCreateReturnsDefaultClient(): void
    {
        $client = VatRetrievalClientFactory::create();

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);

        // Assert the configuration values
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(30, $config->timeout);
        $this->assertFalse($config->debug);
    }

    public function testCreateWithCustomConfigurationAndLogger(): void
    {
        $config = ClientConfiguration::production($this->logger)
            ->withEndpoint('https://custom.endpoint.com')
            ->withTimeout(45);

        $client = VatRetrievalClientFactory::create($config, $this->logger);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert custom configuration is preserved
        $clientConfig = $client->getConfiguration();
        $this->assertSame('https://custom.endpoint.com', $clientConfig->endpoint);
        $this->assertSame(45, $clientConfig->timeout);
        $this->assertSame($this->logger, $clientConfig->logger);
    }

    public function testCreateForTestingReturnsTestClient(): void
    {
        $client = VatRetrievalClientFactory::createForTesting($this->logger);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);

        // Assert test configuration values
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_TEST, $config->endpoint);
        $this->assertSame(15, $config->timeout);
        $this->assertTrue($config->debug);
        $this->assertSame($this->logger, $config->logger);
    }

    public function testCreateForTestingWithProductionEndpoint(): void
    {
        $client = VatRetrievalClientFactory::createForTesting($this->logger, false);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert production endpoint is used when useTestEndpoint=false
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(15, $config->timeout);
        $this->assertTrue($config->debug);
    }

    public function testCreateWithTelemetryIntegratesTelemetry(): void
    {
        $client = VatRetrievalClientFactory::createWithTelemetry($this->telemetry);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert telemetry integration and default production values
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(30, $config->timeout);
        $this->assertSame($this->telemetry, $config->telemetry);
    }

    public function testCreateWithTelemetryAndCustomConfig(): void
    {
        $config = ClientConfiguration::production($this->logger)
            ->withTimeout(60);

        $client = VatRetrievalClientFactory::createWithTelemetry(
            $this->telemetry,
            $config,
            $this->logger
        );

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert custom configuration is preserved with telemetry
        $clientConfig = $client->getConfiguration();
        $this->assertSame(60, $clientConfig->timeout);
        $this->assertSame($this->telemetry, $clientConfig->telemetry);
        $this->assertSame($this->logger, $clientConfig->logger);
    }

    public function testCreateWithEventSubscribersAddsSubscribers(): void
    {
        $subscribers = [$this->eventSubscriber];

        $client = VatRetrievalClientFactory::createWithEventSubscribers($subscribers);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
    }

    public function testCreateWithEventSubscribersThrowsExceptionForInvalidSubscriber(): void
    {
        $invalidSubscriber = new \stdClass();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('All event subscribers must implement EventSubscriberInterface');

        VatRetrievalClientFactory::createWithEventSubscribers([$invalidSubscriber]);
    }

    public function testCreateForEnvironmentProduction(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('production');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert production environment configuration
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(30, $config->timeout);
        $this->assertFalse($config->debug);
    }

    public function testCreateForEnvironmentStaging(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('staging', $this->logger);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert staging environment configuration (production endpoint, debug enabled)
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(45, $config->timeout);
        $this->assertTrue($config->debug);
        $this->assertSame($this->logger, $config->logger);
    }

    public function testCreateForEnvironmentDevelopment(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('development');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert development environment configuration
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_TEST, $config->endpoint);
        $this->assertSame(15, $config->timeout);
        $this->assertTrue($config->debug);
    }

    public function testCreateForEnvironmentTesting(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('testing');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert testing environment configuration
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_TEST, $config->endpoint);
        $this->assertSame(10, $config->timeout);
        $this->assertTrue($config->debug);
    }

    public function testCreateForEnvironmentWithCustomOptions(): void
    {
        $options = [
            'timeout' => 120,
            'debug' => false
        ];

        $client = VatRetrievalClientFactory::createForEnvironment('production', $this->logger, $options);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);

        // Assert custom options override defaults
        $config = $client->getConfiguration();
        $this->assertSame(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertSame(120, $config->timeout);
        $this->assertFalse($config->debug); // Custom option overrides default
        $this->assertSame($this->logger, $config->logger);
    }

    public function testCreateForEnvironmentThrowsExceptionForUnsupportedEnvironment(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported environment: invalid');

        VatRetrievalClientFactory::createForEnvironment('invalid');
    }

    public function testCreateForEnvironmentHandlesCaseInsensitiveEnvironmentNames(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('PRODUCTION');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
    }

    public function testCreateForEnvironmentDevAlias(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('dev');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
    }

    public function testCreateForEnvironmentTestAlias(): void
    {
        $client = VatRetrievalClientFactory::createForEnvironment('test');

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
    }
}
