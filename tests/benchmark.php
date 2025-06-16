<?php

declare(strict_types=1);

/**
 * Performance benchmark script for EU VAT SDK
 *
 * This script measures performance characteristics including response times,
 * memory usage, and throughput for various operations.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "=== EU VAT SDK Performance Benchmarks ===\n\n";

// Set up logger
$logger = new Logger('benchmark');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

// Configure VCR for benchmarks
require_once __DIR__ . '/fixtures/vcr-config.php';
\VCR\VCR::insertCassette('benchmark-performance');

$results = [];

// Benchmark 1: Client initialization time
echo "1. Client Initialization Benchmark\n";
$iterations = 100;
$times = [];

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    $client = VatRetrievalClientFactory::create();
    $times[] = (microtime(true) - $start) * 1000; // Convert to ms
}

$avgInit = array_sum($times) / count($times);
$minInit = min($times);
$maxInit = max($times);

echo "   Iterations: $iterations\n";
echo "   Average: " . round($avgInit, 2) . "ms\n";
echo "   Min: " . round($minInit, 2) . "ms\n";
echo "   Max: " . round($maxInit, 2) . "ms\n";

$results['initialization'] = [
    'avg_ms' => $avgInit,
    'min_ms' => $minInit,
    'max_ms' => $maxInit,
];

// Benchmark 2: Single country request
echo "\n2. Single Country Request Benchmark\n";
$client = VatRetrievalClientFactory::create();
$times = [];
$memorySamples = [];

for ($i = 0; $i < 50; $i++) {
    $memStart = memory_get_usage();
    $start = microtime(true);

    $request = new VatRatesRequest(['DE'], new \DateTime('2024-01-01'));
    $response = $client->retrieveVatRates($request);

    $duration = (microtime(true) - $start) * 1000;
    $memUsed = (memory_get_usage() - $memStart) / 1024; // KB

    $times[] = $duration;
    $memorySamples[] = $memUsed;

    // Small delay to avoid hammering the service
    usleep(100000); // 100ms
}

$avgSingle = array_sum($times) / count($times);
$avgMemory = array_sum($memorySamples) / count($memorySamples);

echo "   Iterations: 50\n";
echo "   Average response time: " . round($avgSingle, 2) . "ms\n";
echo "   Average memory usage: " . round($avgMemory, 2) . "KB\n";

$results['single_country'] = [
    'avg_ms' => $avgSingle,
    'avg_memory_kb' => $avgMemory,
];

// Benchmark 3: Multiple countries request
echo "\n3. Multiple Countries Request Benchmark\n";
$countries = ['DE', 'FR', 'IT', 'ES', 'NL'];
$times = [];
$memorySamples = [];

for ($i = 0; $i < 20; $i++) {
    $memStart = memory_get_usage();
    $start = microtime(true);

    $request = new VatRatesRequest($countries, new \DateTime('2024-01-01'));
    $response = $client->retrieveVatRates($request);

    $duration = (microtime(true) - $start) * 1000;
    $memUsed = (memory_get_usage() - $memStart) / 1024;

    $times[] = $duration;
    $memorySamples[] = $memUsed;

    usleep(200000); // 200ms delay
}

$avgMultiple = array_sum($times) / count($times);
$avgMemoryMultiple = array_sum($memorySamples) / count($memorySamples);

echo "   Countries: " . implode(', ', $countries) . "\n";
echo "   Iterations: 20\n";
echo "   Average response time: " . round($avgMultiple, 2) . "ms\n";
echo "   Average memory usage: " . round($avgMemoryMultiple, 2) . "KB\n";

$results['multiple_countries'] = [
    'countries_count' => count($countries),
    'avg_ms' => $avgMultiple,
    'avg_memory_kb' => $avgMemoryMultiple,
];

// Benchmark 4: All EU countries
echo "\n4. All EU Countries Request Benchmark\n";
$allEuMembers = [
    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
    'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
    'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
];

$memStart = memory_get_usage();
$start = microtime(true);

$request = new VatRatesRequest($allEuMembers, new \DateTime('2024-01-01'));
$response = $client->retrieveVatRates($request);

$duration = (microtime(true) - $start) * 1000;
$memUsed = (memory_get_usage() - $memStart) / 1024 / 1024; // MB
$peakMemory = memory_get_peak_usage() / 1024 / 1024; // MB

echo "   Countries: " . count($allEuMembers) . " (all EU members)\n";
echo "   Response time: " . round($duration, 2) . "ms\n";
echo "   Memory used: " . round($memUsed, 2) . "MB\n";
echo "   Peak memory: " . round($peakMemory, 2) . "MB\n";
echo "   Results count: " . count($response->getResults()) . "\n";

$results['all_eu_countries'] = [
    'countries_count' => count($allEuMembers),
    'response_ms' => $duration,
    'memory_mb' => $memUsed,
    'peak_memory_mb' => $peakMemory,
];

// Benchmark 5: Error handling performance
echo "\n5. Error Handling Performance\n";
$errorTimes = [];

for ($i = 0; $i < 20; $i++) {
    $start = microtime(true);

    try {
        $request = new VatRatesRequest(['XX', 'YY'], new \DateTime('2024-01-01'));
        $client->retrieveVatRates($request);
    } catch (\Exception) {
        // Expected error
    }

    $errorTimes[] = (microtime(true) - $start) * 1000;
    usleep(100000);
}

$avgError = array_sum($errorTimes) / count($errorTimes);

echo "   Error scenario: Invalid country codes\n";
echo "   Iterations: 20\n";
echo "   Average error handling time: " . round($avgError, 2) . "ms\n";

$results['error_handling'] = [
    'avg_ms' => $avgError,
];

// Benchmark 6: Concurrent simulation
echo "\n6. Concurrent Request Simulation\n";
$concurrentRequests = 5;
$start = microtime(true);

for ($i = 0; $i < $concurrentRequests; $i++) {
    $request = new VatRatesRequest(['DE', 'FR'], new \DateTime('2024-01-01'));
    $response = $client->retrieveVatRates($request);
}

$totalTime = (microtime(true) - $start) * 1000;
$avgPerRequest = $totalTime / $concurrentRequests;

echo "   Sequential requests: $concurrentRequests\n";
echo "   Total time: " . round($totalTime, 2) . "ms\n";
echo "   Average per request: " . round($avgPerRequest, 2) . "ms\n";

$results['concurrent_simulation'] = [
    'requests' => $concurrentRequests,
    'total_ms' => $totalTime,
    'avg_per_request_ms' => $avgPerRequest,
];

// Benchmark 7: BigDecimal operations
echo "\n7. BigDecimal Financial Calculations\n";
$iterations = 10000;
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $net = \Brick\Math\BigDecimal::of('999.99');
    $rate = \Brick\Math\BigDecimal::of('19.0');
    $vat = $net->multipliedBy($rate)->dividedBy('100', 2);
    $gross = $net->plus($vat);
}

$calcTime = (microtime(true) - $start) * 1000;
$avgCalc = $calcTime / $iterations * 1000; // Convert to microseconds

echo "   Iterations: " . number_format($iterations) . "\n";
echo "   Total time: " . round($calcTime, 2) . "ms\n";
echo "   Average per calculation: " . round($avgCalc, 2) . "μs\n";

$results['bigdecimal_calculations'] = [
    'iterations' => $iterations,
    'total_ms' => $calcTime,
    'avg_per_calc_us' => $avgCalc,
];

// Summary
echo "\n=== Performance Summary ===\n";
echo "Client initialization: " . round($results['initialization']['avg_ms'], 2) . "ms avg\n";
echo "Single country request: " . round($results['single_country']['avg_ms'], 2) . "ms avg\n";
echo "Multiple countries (5): " . round($results['multiple_countries']['avg_ms'], 2) . "ms avg\n";
echo "All EU countries (27): " . round($results['all_eu_countries']['response_ms'], 2) . "ms\n";
echo "Error handling: " . round($results['error_handling']['avg_ms'], 2) . "ms avg\n";
echo "Memory usage (all EU): " . round($results['all_eu_countries']['memory_mb'], 2) . "MB\n";
echo "BigDecimal calculation: " . round($results['bigdecimal_calculations']['avg_per_calc_us'], 2) . "μs per operation\n";

// Performance recommendations
echo "\n=== Performance Recommendations ===\n";
echo "✓ Client initialization is fast (~" . round($avgInit, 0) . "ms)\n";
echo "✓ Single country requests complete in ~" . round($avgSingle, 0) . "ms\n";
echo "✓ Memory usage is efficient (< " . round($results['all_eu_countries']['memory_mb'], 0) . "MB for all EU)\n";
echo "✓ BigDecimal calculations are performant (~" . round($avgCalc, 0) . "μs per operation)\n";

if ($avgSingle > 100) {
    echo "⚠ Consider implementing caching for frequently accessed rates\n";
}

if ($results['all_eu_countries']['memory_mb'] > 10) {
    echo "⚠ Memory usage might be high for constrained environments\n";
}

// Save results
$resultsFile = __DIR__ . '/benchmark-results.json';
file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT));
echo "\nResults saved to: benchmark-results.json\n";

// Cleanup
\VCR\VCR::eject();
\VCR\VCR::turnOff();
