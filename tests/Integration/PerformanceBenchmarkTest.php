<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Tests\Fixtures\TestDataProvider;

/**
 * Performance benchmark tests for EU VAT SDK
 * 
 * @group integration
 * @group performance
 * @group slow
 * 
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class PerformanceBenchmarkTest extends IntegrationTestCase
{
    /**
     * Benchmark single country request performance
     * 
     * @test
     */
    public function testSingleCountryPerformance(): void
    {
        $cassetteName = 'benchmark-single-country';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
        
        $iterations = 100;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $request = new VatRatesRequest(
                memberStates: ['DE'],
                situationOn: new DateTime('2024-01-01')
            );
            
            $startTime = microtime(true);
            $response = $this->client->retrieveVatRates($request);
            $endTime = microtime(true);
            
            $times[] = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Basic validation
            $this->assertCount(1, $response->getResults());
        }
        
        // Calculate statistics
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        
        $this->logger->info('Single country performance results', [
            'iterations' => $iterations,
            'avg_time_ms' => round($avgTime, 2),
            'min_time_ms' => round($minTime, 2),
            'max_time_ms' => round($maxTime, 2),
        ]);
        
        // Performance assertions (adjust based on your requirements)
        $this->assertLessThan(50, $avgTime, 'Average response time should be under 50ms');
        $this->assertLessThan(100, $maxTime, 'Maximum response time should be under 100ms');
    }
    
    /**
     * Benchmark multiple country batch request performance
     * 
     * @test
     */
    public function testBatchRequestPerformance(): void
    {
        $cassetteName = 'benchmark-batch-request';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
        
        $testConfigs = TestDataProvider::getPerformanceTestConfigs();
        $config = $testConfigs['medium_batch'];
        
        $countries = array_slice(TestDataProvider::EU_MEMBER_STATES, 0, $config['batch_size']);
        $times = [];
        
        for ($i = 0; $i < $config['iterations']; $i++) {
            $request = new VatRatesRequest(
                memberStates: $countries,
                situationOn: new DateTime('2024-01-01')
            );
            
            $startTime = microtime(true);
            $response = $this->client->retrieveVatRates($request);
            $endTime = microtime(true);
            
            $times[] = ($endTime - $startTime) * 1000;
            
            $this->assertCount($config['batch_size'], $response->getResults());
        }
        
        $avgTime = array_sum($times) / count($times);
        $avgTimePerCountry = $avgTime / $config['batch_size'];
        
        $this->logger->info('Batch request performance results', [
            'batch_size' => $config['batch_size'],
            'iterations' => $config['iterations'],
            'avg_time_ms' => round($avgTime, 2),
            'avg_time_per_country_ms' => round($avgTimePerCountry, 2),
        ]);
        
        // Batch requests should be efficient
        $this->assertLessThan(10, $avgTimePerCountry, 'Average time per country should be under 10ms in batch');
    }
    
    /**
     * Benchmark memory usage for large responses
     * 
     * @test
     */
    public function testMemoryUsageForLargeResponses(): void
    {
        $cassetteName = 'benchmark-memory-usage';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
        
        $memoryBefore = memory_get_usage(true);
        
        // Request all EU countries
        $request = new VatRatesRequest(
            memberStates: TestDataProvider::EU_MEMBER_STATES,
            situationOn: new DateTime('2024-01-01')
        );
        
        $response = $this->client->retrieveVatRates($request);
        
        $memoryAfter = memory_get_usage(true);
        $peakMemoryAfter = memory_get_peak_usage(true);
        
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // In MB
        
        $this->logger->info('Memory usage results', [
            'countries_requested' => count(TestDataProvider::EU_MEMBER_STATES),
            'memory_increase_mb' => round($memoryUsed, 2),
            'peak_memory_after_op_mb' => round($peakMemoryAfter / 1024 / 1024, 2),
        ]);
        
        // Memory usage assertions
        $this->assertLessThan(10, $memoryUsed, 'Memory usage increase should be under 10MB for full EU response');
        // Assert against the total peak memory, which is a more stable metric
        $this->assertLessThan(32, $peakMemoryAfter / 1024 / 1024, 'Total peak memory usage should be under 32MB');
        
        // Verify response integrity
        $this->assertCount(27, $response->getResults());
    }
    
    /**
     * Benchmark sequential execution of multiple different requests
     * 
     * @test
     */
    public function testSequentialBatchPerformance(): void
    {
        $cassetteName = 'benchmark-sequential-batch';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
        
        $countryGroups = TestDataProvider::getCountryGroups();
        $requests = [];
        
        // Prepare multiple different requests
        foreach (['benelux', 'nordic', 'baltic'] as $group) {
            $requests[] = new VatRatesRequest(
                memberStates: $countryGroups[$group],
                situationOn: new DateTime('2024-01-01')
            );
        }
        
        // Time sequential execution
        $sequentialStart = microtime(true);
        $sequentialResponses = [];
        
        foreach ($requests as $request) {
            $sequentialResponses[] = $this->client->retrieveVatRates($request);
        }
        
        $sequentialTime = (microtime(true) - $sequentialStart) * 1000;
        
        $this->logger->info('Sequential execution performance', [
            'request_count' => count($requests),
            'total_time_ms' => round($sequentialTime, 2),
            'avg_time_per_request_ms' => round($sequentialTime / count($requests), 2),
        ]);
        
        // Verify all responses
        foreach ($sequentialResponses as $index => $response) {
            $expectedCount = count($requests[$index]->getMemberStates());
            $this->assertCount($expectedCount, $response->getResults());
        }
    }
    
    /**
     * Benchmark date range queries (multiple historical dates)
     * 
     * @test
     */
    public function testDateRangePerformance(): void
    {
        $cassetteName = 'benchmark-date-range';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
        
        $testDates = TestDataProvider::getTestDates();
        $countries = ['DE', 'FR', 'IT'];
        $times = [];
        
        foreach ($testDates as $key => $dateInfo) {
            $request = new VatRatesRequest(
                memberStates: $countries,
                situationOn: new DateTime($dateInfo['date'])
            );
            
            $startTime = microtime(true);
            
            try {
                $response = $this->client->retrieveVatRates($request);
                $responseTime = (microtime(true) - $startTime) * 1000;
                $times[$key] = $responseTime;
                
                $this->logger->debug("Date range test: {$dateInfo['description']}", [
                    'date' => $dateInfo['date'],
                    'response_time_ms' => round($responseTime, 2),
                    'result_count' => count($response->getResults()),
                ]);
            } catch (\Exception $e) {
                // Some dates might fail (e.g., future dates)
                $this->logger->warning("Date range test failed: {$dateInfo['description']}", [
                    'date' => $dateInfo['date'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        if (!empty($times)) {
            $avgTime = array_sum($times) / count($times);
            $this->logger->info('Date range performance summary', [
                'dates_tested' => count($times),
                'avg_response_time_ms' => round($avgTime, 2),
            ]);
            
            // Historical queries should still be performant
            $this->assertLessThan(100, $avgTime, 'Average response time for date queries should be under 100ms');
        }
    }
}