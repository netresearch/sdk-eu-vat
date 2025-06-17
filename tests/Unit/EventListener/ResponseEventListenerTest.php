<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\EventListener;

use Netresearch\EuVatSdk\EventListener\ResponseEventListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test ResponseEventListener response logging
 */
class ResponseEventListenerTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testLogResponseInProductionModeLogsAtInfoLevel(): void
    {
        $listener = new ResponseEventListener($this->logger, false); // Production mode
        $startTime = microtime(true);
        $endTime = $startTime + 1.5; // 1.5 second response
        $response = ['result' => 'success'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'EU VAT SOAP Response received',
                $this->callback(fn($context): bool => $context['method'] === 'retrieveVatRates'
                    && $context['duration_ms'] > 1000
                    && $context['response_type'] === 'array')
            );

        $listener->logResponse('retrieveVatRates', $response, $startTime, $endTime);
    }

    public function testLogResponseInDebugModeLogsWithDetailedAnalysis(): void
    {
        $listener = new ResponseEventListener($this->logger, true); // Debug mode
        $startTime = microtime(true);
        $endTime = $startTime + 0.5; // 500ms response
        $response = (object) ['data' => 'test'];

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'EU VAT SOAP Response received with analysis',
                $this->callback(fn($context): bool => isset($context['response_size_bytes'])
                    && isset($context['response_structure'])
                    && isset($context['memory_usage_after']))
            );

        $listener->logResponse('retrieveVatRates', $response, $startTime, $endTime);
    }

    public function testLogResponseWithSlowResponseLogsWarning(): void
    {
        $listener = new ResponseEventListener($this->logger, false);
        $startTime = microtime(true);
        $endTime = $startTime + 6.0; // 6 second response (slow)
        $response = 'test';

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Slow EU VAT SOAP Response detected',
                $this->callback(fn($context): bool => $context['duration_ms'] > 5000)
            );

        $listener->logResponse('retrieveVatRates', $response, $startTime, $endTime);
    }

    public function testLogResponseWithAdditionalContext(): void
    {
        $listener = new ResponseEventListener($this->logger, false);
        $startTime = microtime(true);
        $endTime = $startTime + 1.0;
        $response = null;
        $context = ['correlation_id' => 'test_123'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'EU VAT SOAP Response received',
                $this->callback(fn($logContext): bool => $logContext['correlation_id'] === 'test_123'
                    && $logContext['response_type'] === 'null')
            );

        $listener->logResponse('retrieveVatRates', $response, $startTime, $endTime, $context);
    }

    public function testLogResponseWithVatRatesResponseObject(): void
    {
        $listener = new ResponseEventListener($this->logger, true); // Debug for structure analysis

        // Create anonymous class with getResults method
        $mockResponse = new class {
            /**
             * @return array<string>
             */
            public function getResults(): array
            {
                return ['result1', 'result2'];
            }
        };

        $startTime = microtime(true);
        $endTime = $startTime + 0.5;

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'EU VAT SOAP Response received with analysis',
                $this->callback(fn($context): bool => isset($context['response_structure']['result_count'])
                    && $context['response_structure']['result_count'] === 2)
            );

        $listener->logResponse('retrieveVatRates', $mockResponse, $startTime, $endTime);
    }

    public function testLogResponseError(): void
    {
        $listener = new ResponseEventListener($this->logger, false);
        $response = ['error' => 'Something went wrong'];

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'EU VAT SOAP Response contains error',
                $this->callback(fn($context): bool => $context['method'] === 'retrieveVatRates'
                    && $context['error_type'] === 'validation_error'
                    && $context['duration_ms'] === 1500.0)
            );

        $listener->logResponseError('retrieveVatRates', $response, 'validation_error', 1500.0);
    }

    public function testLogResponseErrorInDebugModeIncludesErrorResponse(): void
    {
        $listener = new ResponseEventListener($this->logger, true); // Debug mode
        $response = ['error' => 'test error', 'code' => 123];

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'EU VAT SOAP Response contains error',
                $this->callback(fn($context): bool => isset($context['error_response'])
                    && $context['error_response']['error'] === 'test error')
            );

        $listener->logResponseError('retrieveVatRates', $response, 'test_error', 1000.0);
    }

    public function testLogPerformanceMetricsWithNormalResponse(): void
    {
        $listener = new ResponseEventListener($this->logger, true); // Debug for metrics

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'EU VAT response performance metrics',
                $this->callback(function (array $context): bool {
                    return $context['duration_ms'] === 2000.0
                        && $context['response_size_bytes'] === 1024
                        && $context['throughput_bytes_per_ms'] === 0.51; // 1024/2000 rounded
                })
            );

        $listener->logPerformanceMetrics('retrieveVatRates', 2000.0, 1024);
    }

    public function testLogPerformanceMetricsWithSlowResponse(): void
    {
        $listener = new ResponseEventListener($this->logger, false);

        $this->logger->expects($this->once())
            ->method('notice')
            ->with(
                'Slow EU VAT response detected',
                $this->callback(function (array $context): bool {
                    return $context['duration_ms'] === 7000.0; // Slow but not very slow
                })
            );

        $listener->logPerformanceMetrics('retrieveVatRates', 7000.0, 512);
    }

    public function testLogPerformanceMetricsWithVerySlowResponse(): void
    {
        $listener = new ResponseEventListener($this->logger, false);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Very slow EU VAT response detected',
                $this->callback(function (array $context): bool {
                    return $context['duration_ms'] === 15000.0; // Very slow
                })
            );

        $listener->logPerformanceMetrics('retrieveVatRates', 15000.0, 2048);
    }

    public function testIsDebugEnabledReturnsCorrectValue(): void
    {
        $productionListener = new ResponseEventListener($this->logger, false);
        $debugListener = new ResponseEventListener($this->logger, true);

        $this->assertFalse($productionListener->isDebugEnabled());
        $this->assertTrue($debugListener->isDebugEnabled());
    }

    public function testLogResponseWithLargeResponseGetsTruncated(): void
    {
        $listener = new ResponseEventListener($this->logger, true); // Debug mode to include error response

        // Create a very large response array
        $largeResponse = [];
        for ($i = 0; $i < 30; $i++) {
            $largeResponse["item_$i"] = "data_$i";
        }

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'EU VAT SOAP Response contains error',
                $this->callback(fn($context): bool =>
                    // Should be truncated due to size
                    isset($context['error_response']['[TRUNCATED]']))
            );

        $listener->logResponseError('retrieveVatRates', $largeResponse, 'large_error', 1000.0);
    }
}
