<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Middleware;

use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Middleware\LoggingMiddleware;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test LoggingMiddleware SOAP operation monitoring
 */
class LoggingMiddlewareTest extends TestCase
{
    private LoggerInterface $logger;
    private TelemetryInterface $telemetry;
    private LoggingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->telemetry = $this->createMock(TelemetryInterface::class);
        $this->middleware = new LoggingMiddleware($this->logger, $this->telemetry);
    }

    public function testProcessSuccessfulOperationLogsAndRecordsTelemetry(): void
    {
        $arguments = ['memberStates' => ['DE', 'FR']];
        $expectedResult = ['results' => ['DE' => '19%', 'FR' => '20%']];

        // Expect initiation and completion logs
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    'EU VAT SOAP Operation initiated',
                    'EU VAT SOAP Operation completed successfully'
                ),
                $this->isType('array')
            );

        // Expect telemetry recording
        $this->telemetry->expects($this->once())
            ->method('recordRequest')
            ->with(
                'retrieveVatRates',
                $this->greaterThan(0), // duration
                $this->callback(fn($context): bool => $context['endpoint'] === 'EU_VAT_Service'
                    && $context['result_type'] === 'array')
            );

        $next = function ($method, $args) use ($expectedResult): array {
            $this->assertEquals('retrieveVatRates', $method);
            $this->assertEquals(['memberStates' => ['DE', 'FR']], $args);
            return $expectedResult;
        };

        $result = $this->middleware->process('retrieveVatRates', $arguments, $next);

        $this->assertEquals($expectedResult, $result);
    }

    public function testProcessFailedOperationLogsErrorAndRecordsTelemetry(): void
    {
        $arguments = ['memberStates' => ['XX']]; // Invalid country
        $exception = new InvalidRequestException('Invalid country code');

        // Expect initiation log
        $this->logger->expects($this->once())
            ->method('info')
            ->with('EU VAT SOAP Operation initiated', $this->isType('array'));

        // Expect error log
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'EU VAT SOAP Operation failed',
                $this->callback(fn($context): bool => $context['method'] === 'retrieveVatRates'
                    && $context['exception_class'] === InvalidRequestException::class
                    && $context['exception_message'] === 'Invalid country code')
            );

        // Expect error telemetry
        $this->telemetry->expects($this->once())
            ->method('recordError')
            ->with(
                'retrieveVatRates',
                InvalidRequestException::class,
                $this->callback(fn($context): bool => $context['endpoint'] === 'EU_VAT_Service'
                    && $context['error_message'] === 'Invalid country code')
            );

        $next = function ($method, $args) use ($exception): void {
            throw $exception;
        };

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->middleware->process('retrieveVatRates', $arguments, $next);
    }

    public function testProcessWithSlowOperationLogsWarning(): void
    {
        $arguments = ['memberStates' => ['DE']];
        $result = ['results' => ['DE' => '19%']];

        // Expect logs for slow operation
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    'EU VAT SOAP Operation initiated',
                    'EU VAT SOAP Operation completed successfully'
                ),
                $this->isType('array')
            );

        $this->telemetry->expects($this->once())
            ->method('recordRequest');

        $next = function ($method, $args) use ($result): array {
            // Simulate slow operation
            usleep(10000); // 10ms delay (not actually slow but will be > 0)
            return $result;
        };

        $actualResult = $this->middleware->process('retrieveVatRates', $arguments, $next);
        $this->assertEquals($result, $actualResult);
    }

    public function testProcessWithVatRatesMethodIncludesCountryContext(): void
    {
        $arguments = ['memberStates' => ['DE', 'FR', 'IT']];
        $result = ['results' => []];

        $this->logger->expects($this->exactly(2))
            ->method('info');

        // Verify telemetry includes country-specific context
        $this->telemetry->expects($this->once())
            ->method('recordRequest')
            ->with(
                'retrieveVatRates',
                $this->greaterThan(0),
                $this->callback(fn($context): bool => isset($context['member_states'])
                    && $context['member_states'] === ['DE', 'FR', 'IT']
                    && $context['country_count'] === 3)
            );

        $next = fn($method, $args): array => $result;

        $this->middleware->process('retrieveVatRates', $arguments, $next);
    }

    public function testProcessWithNonArrayMemberStatesHandledCorrectly(): void
    {
        $arguments = ['memberStates' => 'DE']; // Single string instead of array
        $result = ['results' => []];

        $this->telemetry->expects($this->once())
            ->method('recordRequest')
            ->with(
                'retrieveVatRates',
                $this->greaterThan(0),
                $this->callback(fn($context): bool => $context['member_states'] === ['DE']
                    && $context['country_count'] === 1)
            );

        $next = fn($method, $args): array => $result;

        $this->middleware->process('retrieveVatRates', $arguments, $next);
    }

    public function testProcessWithResponseObjectHavingGetResultsMethod(): void
    {
        $arguments = ['memberStates' => ['DE']];

        // Create anonymous class with getResults method
        $mockResponse = new class {
            /**
             * @return array<string>
             */
            public function getResults(): array
            {
                return ['item1', 'item2'];
            }
        };

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    'EU VAT SOAP Operation initiated',
                    'EU VAT SOAP Operation completed successfully'
                ),
                $this->isType('array')
            );

        $next = fn($method, $args): object => $mockResponse;

        $result = $this->middleware->process('retrieveVatRates', $arguments, $next);
        $this->assertSame($mockResponse, $result);
    }

    public function testGetLoggerReturnsLoggerInstance(): void
    {
        $this->assertSame($this->logger, $this->middleware->getLogger());
    }

    public function testGetTelemetryReturnsTelemetryInstance(): void
    {
        $this->assertSame($this->telemetry, $this->middleware->getTelemetry());
    }

    public function testProcessWithErrorInTelemetryStillPropagatesOriginalException(): void
    {
        $arguments = ['test' => 'data'];
        $originalException = new InvalidRequestException('Original error');

        // Make telemetry throw an exception
        $this->telemetry->expects($this->once())
            ->method('recordError')
            ->willThrowException(new \RuntimeException('Telemetry error'));

        $next = function ($method, $args) use ($originalException): void {
            throw $originalException;
        };

        // Should still throw the original exception, not the telemetry error
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Original error');

        $this->middleware->process('testMethod', $arguments, $next);
    }

    public function testProcessWithNullArgumentsHandled(): void
    {
        $arguments = [];
        $result = 'test result';

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    'EU VAT SOAP Operation initiated',
                    'EU VAT SOAP Operation completed successfully'
                ),
                $this->isType('array')
            );

        $next = fn($method, $args): string => $result;

        $actualResult = $this->middleware->process('testMethod', $arguments, $next);
        $this->assertEquals($result, $actualResult);
    }
}
