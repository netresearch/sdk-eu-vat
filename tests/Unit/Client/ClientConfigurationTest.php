<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Telemetry\NullTelemetry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Netresearch\EuVatSdk\Middleware\MiddlewareInterface;

/**
 * Test ClientConfiguration immutable object
 */
class ClientConfigurationTest extends TestCase
{
    public function testProductionFactoryMethod(): void
    {
        $config = ClientConfiguration::production();

        $this->assertEquals(ClientConfiguration::ENDPOINT_PRODUCTION, $config->endpoint);
        $this->assertEquals(ClientConfiguration::DEFAULT_TIMEOUT, $config->timeout);
        $this->assertFalse($config->debug);
        $this->assertInstanceOf(NullLogger::class, $config->logger);
        $this->assertInstanceOf(NullTelemetry::class, $config->telemetry);
        $this->assertNull($config->wsdlPath);
        $this->assertEmpty($config->eventSubscribers);
        $this->assertEmpty($config->middleware);
    }

    public function testTestFactoryMethod(): void
    {
        $config = ClientConfiguration::test();

        $this->assertEquals(ClientConfiguration::ENDPOINT_TEST, $config->endpoint);
        $this->assertEquals(ClientConfiguration::DEFAULT_TIMEOUT, $config->timeout);
        $this->assertTrue($config->debug); // Debug enabled for test
        $this->assertInstanceOf(NullLogger::class, $config->logger);
        $this->assertInstanceOf(NullTelemetry::class, $config->telemetry);
    }

    public function testProductionWithCustomLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $config = ClientConfiguration::production($logger);

        $this->assertSame($logger, $config->logger);
    }

    public function testWithTimeoutImmutability(): void
    {
        $original = ClientConfiguration::production();
        $modified = $original->withTimeout(60);

        // Original should be unchanged
        $this->assertEquals(ClientConfiguration::DEFAULT_TIMEOUT, $original->timeout);

        // Modified should have new timeout
        $this->assertEquals(60, $modified->timeout);

        // Other properties should be preserved
        $this->assertEquals($original->endpoint, $modified->endpoint);
        $this->assertEquals($original->debug, $modified->debug);
    }

    public function testWithDebugImmutability(): void
    {
        $original = ClientConfiguration::production();
        $modified = $original->withDebug(true);

        // Original should be unchanged
        $this->assertFalse($original->debug);

        // Modified should have debug enabled
        $this->assertTrue($modified->debug);

        // Other properties should be preserved
        $this->assertEquals($original->endpoint, $modified->endpoint);
        $this->assertEquals($original->timeout, $modified->timeout);
    }

    public function testWithLoggerImmutability(): void
    {
        $original = ClientConfiguration::production();
        $testLogger = $this->createMock(LoggerInterface::class);
        $modified = $original->withLogger($testLogger);

        // Original should be unchanged
        $this->assertInstanceOf(NullLogger::class, $original->logger);

        // Modified should have new logger
        $this->assertSame($testLogger, $modified->logger);
    }

    public function testWithWsdlPathImmutability(): void
    {
        $original = ClientConfiguration::production();
        $wsdlPath = __DIR__ . '/../../fixtures/test.wsdl';

        // Create a temporary WSDL file for testing
        if (!is_dir(dirname($wsdlPath))) {
            mkdir(dirname($wsdlPath), 0755, true);
        }
        file_put_contents($wsdlPath, '<?xml version="1.0"?><definitions />');

        try {
            $modified = $original->withWsdlPath($wsdlPath);

            // Original should be unchanged
            $this->assertNull($original->wsdlPath);

            // Modified should have WSDL path
            $this->assertEquals($wsdlPath, $modified->wsdlPath);
        } finally {
            unlink($wsdlPath);
        }
    }

    public function testWithSoapOptionsImmutability(): void
    {
        $original = ClientConfiguration::production();
        $customOptions = ['user_agent' => 'TestAgent/1.0'];
        $modified = $original->withSoapOptions($customOptions);

        // Modified should have merged options
        $this->assertArrayHasKey('user_agent', $modified->soapOptions);
        $this->assertEquals('TestAgent/1.0', $modified->soapOptions['user_agent']);

        // Should preserve default SOAP options
        $this->assertArrayHasKey('connection_timeout', $modified->soapOptions);
        $this->assertEquals($original->timeout, $modified->soapOptions['connection_timeout']);
    }

    public function testWithEventSubscriberImmutability(): void
    {
        $original = ClientConfiguration::production();
        $subscriber = new \stdClass();
        $modified = $original->withEventSubscriber($subscriber);

        // Original should be unchanged
        $this->assertEmpty($original->eventSubscribers);

        // Modified should have subscriber
        $this->assertCount(1, $modified->eventSubscribers);
        $this->assertSame($subscriber, $modified->eventSubscribers[0]);
    }

    public function testWithMiddlewareImmutability(): void
    {
        $original = ClientConfiguration::production();
        $middleware = $this->createMock(MiddlewareInterface::class);
        $modified = $original->withMiddleware($middleware);

        // Original should be unchanged
        $this->assertEmpty($original->middleware);

        // Modified should have middleware
        $this->assertCount(1, $modified->middleware);
        $this->assertSame($middleware, $modified->middleware[0]);
    }

    public function testChainedWithMethods(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $subscriber = new \stdClass();
        $middleware = $this->createMock(MiddlewareInterface::class);

        $config = ClientConfiguration::production()
            ->withTimeout(45)
            ->withDebug(true)
            ->withLogger($logger)
            ->withEventSubscriber($subscriber)
            ->withMiddleware($middleware);

        $this->assertEquals(45, $config->timeout);
        $this->assertTrue($config->debug);
        $this->assertSame($logger, $config->logger);
        $this->assertSame($subscriber, $config->eventSubscribers[0]);
        $this->assertSame($middleware, $config->middleware[0]);
    }

    public function testInvalidEndpointThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid endpoint URL');

        // Use reflection to test private constructor with invalid endpoint
        $reflection = new \ReflectionClass(ClientConfiguration::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);

        $constructor->invoke(
            $reflection->newInstanceWithoutConstructor(),
            'not-a-url',
            [],
            30,
            false,
            new NullLogger(),
            null,
            new NullTelemetry(),
            [],
            []
        );
    }

    public function testInvalidTimeoutThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Timeout must be between 1 and');

        ClientConfiguration::production()->withTimeout(0);
    }

    public function testExcessiveTimeoutThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Timeout must be between 1 and');

        ClientConfiguration::production()->withTimeout(400);
    }

    public function testNonExistentWsdlPathThrowsException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('WSDL file not found');

        ClientConfiguration::production()->withWsdlPath('/non/existent/file.wsdl');
    }

    public function testSoapOptionsIncludeTimeout(): void
    {
        $config = ClientConfiguration::production()->withTimeout(60);

        $this->assertArrayHasKey('connection_timeout', $config->soapOptions);
        $this->assertEquals(60, $config->soapOptions['connection_timeout']);
    }

    public function testDefaultSoapOptions(): void
    {
        $config = ClientConfiguration::production();

        $expectedOptions = [
            'connection_timeout' => ClientConfiguration::DEFAULT_TIMEOUT,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'soap_version' => SOAP_1_1,
            'trace' => false,
            'exceptions' => true,
        ];

        foreach ($expectedOptions as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $config->soapOptions);
            $this->assertEquals($expectedValue, $config->soapOptions[$key]);
        }
    }

    public function testDebugModeAffectsSoapTrace(): void
    {
        $debugConfig = ClientConfiguration::production()->withDebug(true);
        $this->assertTrue($debugConfig->soapOptions['trace']);

        $normalConfig = ClientConfiguration::production()->withDebug(false);
        $this->assertFalse($normalConfig->soapOptions['trace']);
    }
}
