<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\EventListener;

use Netresearch\EuVatSdk\EventListener\RequestEventListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test RequestEventListener request logging
 */
class RequestEventListenerTest extends TestCase
{
    private LoggerInterface $logger;
    
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }
    
    public function testLogRequestInProductionModeLogsAtInfoLevel(): void
    {
        $listener = new RequestEventListener($this->logger, false); // Production mode
        $startTime = microtime(true);
        $arguments = ['memberStates' => ['DE', 'FR'], 'situationOn' => '2024-01-15'];
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'EU VAT SOAP Request initiated',
                $this->callback(function($context) {
                    return $context['method'] === 'retrieveVatRates'
                        && $context['arguments_count'] === 2
                        && isset($context['request_time']);
                })
            );
        
        $listener->logRequest('retrieveVatRates', $arguments, $startTime);
    }
    
    public function testLogRequestInDebugModeLogsAtDebugLevel(): void
    {
        $listener = new RequestEventListener($this->logger, true); // Debug mode
        $startTime = microtime(true);
        $arguments = ['memberStates' => ['DE', 'FR']];
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'EU VAT SOAP Request initiated with detailed payload',
                $this->callback(function($context) {
                    return $context['method'] === 'retrieveVatRates'
                        && isset($context['arguments'])
                        && isset($context['memory_usage']);
                })
            );
        
        $listener->logRequest('retrieveVatRates', $arguments, $startTime);
    }
    
    public function testLogRequestWithAdditionalContext(): void
    {
        $listener = new RequestEventListener($this->logger, false);
        $startTime = microtime(true);
        $arguments = ['test' => 'data'];
        $context = ['correlation_id' => 'test_123'];
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'EU VAT SOAP Request initiated',
                $this->callback(function($logContext) {
                    return $logContext['correlation_id'] === 'test_123';
                })
            );
        
        $listener->logRequest('retrieveVatRates', $arguments, $startTime, $context);
    }
    
    public function testLogRequestTimingWithNormalTime(): void
    {
        $listener = new RequestEventListener($this->logger, true); // Debug mode for timing logs
        $startTime = microtime(true);
        $preparedTime = $startTime + 0.05; // 50ms preparation time
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'SOAP request preparation timing',
                $this->callback(function($context) {
                    return $context['method'] === 'retrieveVatRates'
                        && $context['preparation_time_ms'] < 100;
                })
            );
        
        $listener->logRequestTiming('retrieveVatRates', $startTime, $preparedTime);
    }
    
    public function testLogRequestTimingWithSlowPreparation(): void
    {
        $listener = new RequestEventListener($this->logger, false);
        $startTime = microtime(true);
        $preparedTime = $startTime + 0.15; // 150ms preparation time (slow)
        
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Slow SOAP request preparation detected',
                $this->callback(function($context) {
                    return $context['preparation_time_ms'] > 100;
                })
            );
        
        $listener->logRequestTiming('retrieveVatRates', $startTime, $preparedTime);
    }
    
    public function testGenerateCorrelationIdReturnsUniqueIds(): void
    {
        $listener = new RequestEventListener($this->logger, false);
        
        $id1 = $listener->generateCorrelationId();
        $id2 = $listener->generateCorrelationId();
        
        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('vat_', $id1);
        $this->assertStringStartsWith('vat_', $id2);
    }
    
    public function testGenerateCorrelationIdIncludesTimestamp(): void
    {
        $listener = new RequestEventListener($this->logger, false);
        
        $correlationId = $listener->generateCorrelationId();
        $today = date('Ymd');
        
        $this->assertStringContainsString($today, $correlationId);
    }
    
    public function testIsDebugEnabledReturnsCorrectValue(): void
    {
        $productionListener = new RequestEventListener($this->logger, false);
        $debugListener = new RequestEventListener($this->logger, true);
        
        $this->assertFalse($productionListener->isDebugEnabled());
        $this->assertTrue($debugListener->isDebugEnabled());
    }
    
    public function testLogRequestWithEmptyArguments(): void
    {
        $listener = new RequestEventListener($this->logger, false);
        $startTime = microtime(true);
        
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'EU VAT SOAP Request initiated',
                $this->callback(function($context) {
                    return $context['arguments_count'] === 0;
                })
            );
        
        $listener->logRequest('test', [], $startTime);
    }
    
    public function testLogRequestWithLargeArgumentsInDebugMode(): void
    {
        $listener = new RequestEventListener($this->logger, true);
        $startTime = microtime(true);
        
        // Create large nested array to test depth limiting
        $largeArray = [];
        for ($i = 0; $i < 60; $i++) {
            $largeArray["key_$i"] = [
                'nested' => [
                    'deep' => [
                        'value' => "data_$i"
                    ]
                ]
            ];
        }
        
        $arguments = ['large_data' => $largeArray];
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'EU VAT SOAP Request initiated with detailed payload',
                $this->callback(function($context) {
                    // Verify that arguments are sanitized/limited
                    return isset($context['arguments']['large_data']['[TRUNCATED]']);
                })
            );
        
        $listener->logRequest('test', $arguments, $startTime);
    }
}