<?php

declare(strict_types=1);

/**
 * Batch processing example for EU VAT SDK
 *
 * This example demonstrates how to efficiently process multiple
 * VAT rate requests and handle large datasets.
 *
 * @package Netresearch\EuVatSdk\Examples
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Brick\Math\BigDecimal;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "=== EU VAT SDK - Batch Processing Example ===\n\n";

// Set up logging for batch operations
$logger = new Logger('vat-batch');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

$client = VatRetrievalClientFactory::create(
    ClientConfiguration::production($logger)
        ->withTimeout(60) // Longer timeout for batch operations
);

// Example 1: Process all EU member states
echo "1. Retrieving VAT rates for all EU member states:\n";

// All EU member states as of 2024
$allEuMembers = [
    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
    'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
    'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
];

try {
    $startTime = microtime(true);
    
    $request = new VatRatesRequest(
        memberStates: $allEuMembers,
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    $results = $response->getResults();
    
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);
    
    echo "   ✓ Retrieved " . count($results) . " VAT rates in {$duration}ms\n";
    
    // Group by VAT rate for analysis
    $rateGroups = [];
    foreach ($results as $result) {
        $rate = $result->getVatRate()->getValue();
        if (!isset($rateGroups[$rate])) {
            $rateGroups[$rate] = [];
        }
        $rateGroups[$rate][] = $result->getMemberState();
    }
    
    echo "   VAT rate distribution:\n";
    ksort($rateGroups);
    foreach ($rateGroups as $rate => $countries) {
        echo "     {$rate}%: " . implode(', ', $countries) . "\n";
    }
    
} catch (VatServiceException $e) {
    echo "   ✗ Batch request failed: " . $e->getMessage() . "\n";
}

// Example 2: Historical analysis (multiple dates)
echo "\n2. Historical VAT rate analysis:\n";

$analysisCountries = ['DE', 'FR', 'IT', 'ES', 'NL'];
$analysisDates = [
    '2020-01-01',
    '2021-01-01', 
    '2022-01-01',
    '2023-01-01',
    '2024-01-01',
];

$historicalData = [];

foreach ($analysisDates as $dateString) {
    echo "   Processing date: $dateString\n";
    
    try {
        $request = new VatRatesRequest(
            memberStates: $analysisCountries,
            situationOn: new DateTime($dateString)
        );
        
        $response = $client->retrieveVatRates($request);
        
        foreach ($response->getResults() as $result) {
            $country = $result->getMemberState();
            $rate = $result->getVatRate()->getValue();
            
            if (!isset($historicalData[$country])) {
                $historicalData[$country] = [];
            }
            $historicalData[$country][$dateString] = $rate;
        }
        
        // Small delay to be respectful to the service
        usleep(100000); // 100ms delay
        
    } catch (VatServiceException $e) {
        echo "     ✗ Failed for $dateString: " . $e->getMessage() . "\n";
    }
}

// Display historical analysis
echo "\n   Historical VAT rate trends:\n";
foreach ($historicalData as $country => $dates) {
    echo "     $country: ";
    $rates = [];
    foreach ($analysisDates as $date) {
        if (isset($dates[$date])) {
            $rates[] = $dates[$date] . '%';
        } else {
            $rates[] = 'N/A';
        }
    }
    echo implode(' → ', $rates) . "\n";
}

// Example 3: Chunk processing for large datasets
echo "\n3. Chunk processing demonstration:\n";

function processCountriesInChunks(
    $client,
    array $countries,
    DateTime $date,
    int $chunkSize = 10
): array {
    $allResults = [];
    $errors = []; // Collect errors for caller
    $chunks = array_chunk($countries, $chunkSize);
    
    echo "   Processing " . count($countries) . " countries in " . count($chunks) . " chunks\n";
    
    foreach ($chunks as $index => $chunk) {
        echo "     Chunk " . ($index + 1) . "/" . count($chunks) . ": " . implode(', ', $chunk) . "\n";
        
        try {
            $request = new VatRatesRequest(
                memberStates: $chunk,
                situationOn: $date
            );
            
            $response = $client->retrieveVatRates($request);
            $allResults = array_merge($allResults, $response->getResults());
            
            // Respectful delay between chunks
            if ($index < count($chunks) - 1) {
                usleep(200000); // 200ms delay between chunks
            }
            
        } catch (VatServiceException $e) {
            echo "       ✗ Chunk failed: " . $e->getMessage() . "\n";
            $errors[] = ['chunk' => $chunk, 'error' => $e->getMessage()]; // Collect error
        }
    }
    
    return ['results' => $allResults, 'errors' => $errors]; // Return both
}

// Process a large list of countries in chunks
$largeCountryList = array_merge($allEuMembers, ['GB', 'US', 'CN']); // Mix valid and invalid

$chunkedData = processCountriesInChunks(
    $client,
    $largeCountryList,
    new DateTime('2024-01-01'),
    5 // Small chunks for demonstration
);

echo "   ✓ Processed " . count($chunkedData['results']) . " results from chunked requests\n";
echo "   ✗ Encountered " . count($chunkedData['errors']) . " errors\n";

// Example 4: VAT calculation batch processing
echo "\n4. Batch VAT calculations:\n";

// Sample product data
$products = [
    ['name' => 'Laptop', 'net_price' => '1000.00', 'country' => 'DE'],
    ['name' => 'Software License', 'net_price' => '299.99', 'country' => 'FR'],
    ['name' => 'Consulting Service', 'net_price' => '150.00', 'country' => 'IT'],
    ['name' => 'Hardware Support', 'net_price' => '75.50', 'country' => 'ES'],
    ['name' => 'Training Course', 'net_price' => '499.00', 'country' => 'NL'],
];

// Get VAT rates for all countries
$countries = array_unique(array_column($products, 'country'));

try {
    $request = new VatRatesRequest(
        memberStates: $countries,
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    
    // Build VAT rate lookup
    $vatRates = [];
    foreach ($response->getResults() as $result) {
        $vatRates[$result->getMemberState()] = $result->getVatRate()->getValue();
    }
    
    echo "   Product pricing with VAT:\n";
    
    $totalNet = BigDecimal::zero();
    $totalVat = BigDecimal::zero();
    $totalGross = BigDecimal::zero();
    
    foreach ($products as $product) {
        $netPrice = BigDecimal::of($product['net_price']);
        $country = $product['country'];
        
        if (isset($vatRates[$country])) {
            $vatRate = $vatRates[$country];
            $vatAmount = $netPrice->multipliedBy($vatRate)->dividedBy('100', 2);
            $grossPrice = $netPrice->plus($vatAmount);
            
            printf(
                "     %-20s (%s): €%s + €%s VAT (%s%%) = €%s\n",
                $product['name'],
                $country,
                $netPrice->__toString(),
                $vatAmount->__toString(),
                $vatRate->__toString(),
                $grossPrice->__toString()
            );
            
            $totalNet = $totalNet->plus($netPrice);
            $totalVat = $totalVat->plus($vatAmount);
            $totalGross = $totalGross->plus($grossPrice);
        } else {
            echo "     {$product['name']} ({$country}): VAT rate not available\n";
        }
    }
    
    echo "   " . str_repeat('-', 60) . "\n";
    printf(
        "     %-20s         €%s + €%s VAT = €%s\n",
        "TOTAL:",
        $totalNet->__toString(),
        $totalVat->__toString(),
        $totalGross->__toString()
    );
    
} catch (VatServiceException $e) {
    echo "   ✗ VAT calculation batch failed: " . $e->getMessage() . "\n";
}

// Example 5: Error handling in batch operations
echo "\n5. Robust batch processing with error handling:\n";

function robustBatchProcessing($client, array $batches): array
{
    $allResults = [];
    $errors = [];
    
    foreach ($batches as $batchIndex => $batch) {
        echo "   Processing batch " . ($batchIndex + 1) . " of " . count($batches) . "\n";
        
        $retryCount = 0;
        $maxRetries = 3;
        
        while ($retryCount < $maxRetries) {
            try {
                $request = new VatRatesRequest(
                    memberStates: $batch['countries'],
                    situationOn: new DateTime($batch['date'])
                );
                
                $response = $client->retrieveVatRates($request);
                $allResults = array_merge($allResults, $response->getResults());
                
                echo "     ✓ Batch completed successfully\n";
                break; // Success, exit retry loop
                
            } catch (VatServiceException $e) {
                $retryCount++;
                
                if ($e instanceof \Netresearch\EuVatSdk\Exception\InvalidRequestException) {
                    // Don't retry client errors
                    echo "     ✗ Client error (not retrying): " . $e->getMessage() . "\n";
                    $errors[] = "Batch $batchIndex: " . $e->getMessage();
                    break;
                }
                
                if ($retryCount < $maxRetries) {
                    echo "     ⚠️  Retry $retryCount/$maxRetries: " . $e->getMessage() . "\n";
                    sleep(1); // Wait before retry
                } else {
                    echo "     ✗ All retries failed: " . $e->getMessage() . "\n";
                    $errors[] = "Batch $batchIndex: " . $e->getMessage();
                }
            }
        }
        
        // Small delay between batches
        usleep(500000); // 500ms
    }
    
    return [
        'results' => $allResults,
        'errors' => $errors,
    ];
}

// Test robust batch processing
$testBatches = [
    ['countries' => ['DE', 'FR'], 'date' => '2024-01-01'],
    ['countries' => ['IT', 'ES'], 'date' => '2024-01-01'],
    ['countries' => ['XX', 'YY'], 'date' => '2024-01-01'], // Invalid - will cause error
    ['countries' => ['NL', 'BE'], 'date' => '2024-01-01'],
];

$batchResult = robustBatchProcessing($client, $testBatches);

echo "   Results summary:\n";
echo "     Successful results: " . count($batchResult['results']) . "\n";
echo "     Errors encountered: " . count($batchResult['errors']) . "\n";

if (!empty($batchResult['errors'])) {
    echo "   Error details:\n";
    foreach ($batchResult['errors'] as $error) {
        echo "     - $error\n";
    }
}

echo "\nBatch processing examples completed!\n";
echo "\nBest Practices for Batch Processing:\n";
echo "- Use reasonable chunk sizes (5-15 countries per request)\n";
echo "- Add delays between requests to be respectful to the service\n";
echo "- Implement retry logic for transient failures\n";
echo "- Don't retry client-side validation errors\n";
echo "- Monitor request rates and adjust accordingly\n";
echo "- Use BigDecimal for all financial calculations\n";
echo "- Log batch operations for monitoring and debugging\n";