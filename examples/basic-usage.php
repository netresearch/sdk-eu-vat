<?php

declare(strict_types=1);

/**
 * Basic usage example for EU VAT SDK
 *
 * This example demonstrates the simplest way to retrieve VAT rates
 * for one or more EU member states.
 *
 * @package Netresearch\EuVatSdk\Examples
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;

// Create a basic client with default configuration
$client = VatRetrievalClientFactory::create();

echo "=== EU VAT SDK - Basic Usage Example ===\n\n";

try {
    // Example 1: Single country VAT rate
    echo "1. Retrieving VAT rate for Germany:\n";
    
    $request = new VatRatesRequest(
        memberStates: ['DE'],
        situationOn: new DateTime('2024-01-01')
    );

    $response = $client->retrieveVatRates($request);

    foreach ($response->getResults() as $result) {
        printf(
            "   %s: %s%% (%s rate)\n",
            $result->getMemberState(),
            $result->getVatRate()->getValue()->__toString(),
            $result->getVatRate()->getType()
        );
    }

    echo "\n";

    // Example 2: Multiple countries
    echo "2. Retrieving VAT rates for multiple countries:\n";
    
    $request = new VatRatesRequest(
        memberStates: ['DE', 'FR', 'IT', 'ES', 'NL'],
        situationOn: new DateTime('2024-01-01')
    );

    $response = $client->retrieveVatRates($request);

    foreach ($response->getResults() as $result) {
        printf(
            "   %s: %s%% (%s)\n",
            $result->getMemberState(),
            $result->getVatRate()->getValue()->__toString(),
            $result->getVatRate()->getType()
        );
    }

    echo "\n";

    // Example 3: Historical rates (Brexit example)
    echo "3. Historical VAT rates (UK before Brexit):\n";
    
    // First, show successful request for GB before Brexit
    $request = new VatRatesRequest(
        memberStates: ['GB'],
        situationOn: new DateTime('2020-01-01')  // Before Brexit
    );

    $response = $client->retrieveVatRates($request);
    foreach ($response->getResults() as $result) {
        printf(
            "   %s (2020): %s%% (%s)\n",
            $result->getMemberState(),
            $result->getVatRate()->getValue()->__toString(),
            $result->getVatRate()->getType()
        );
    }
    
    echo "\n   Now trying GB after Brexit (this will fail):\n";
    
    // Second, show expected error for GB after Brexit
    $postBrexitRequest = new VatRatesRequest(
        memberStates: ['GB'],
        situationOn: new DateTime('2022-01-01')  // After Brexit
    );

    try {
        $response = $client->retrieveVatRates($postBrexitRequest);
        echo "   Unexpected: GB request succeeded after Brexit\n";
    } catch (InvalidRequestException $e) {
        echo "   ✓ Expected error for GB after Brexit: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // Example 4: Using the response data
    echo "4. Working with response data:\n";
    
    $request = new VatRatesRequest(
        memberStates: ['DE', 'FR'],
        situationOn: new DateTime('2024-01-01')
    );

    $response = $client->retrieveVatRates($request);

    // Access specific country results
    $results = $response->getResults();
    echo "   Total results: " . count($results) . "\n";

    // Find specific country
    foreach ($results as $result) {
        if ($result->getMemberState() === 'DE') {
            $vatRate = $result->getVatRate();
            
            echo "   Germany details:\n";
            echo "     - Country: " . $result->getMemberState() . "\n";
            echo "     - Rate: " . $vatRate->getValue()->__toString() . "%\n";
            echo "     - Type: " . $vatRate->getType() . "\n";
            echo "     - Date: " . $result->getSituationOn()->format('Y-m-d') . "\n";
            
            // Get precise decimal value for calculations
            $decimalRate = $vatRate->getValue();
            echo "     - Decimal rate: " . $decimalRate->__toString() . "\n";
            
            break;
        }
    }

    echo "\nSuccess! Retrieved VAT rates from EU service.\n";

} catch (VatServiceException $e) {
    echo "Error retrieving VAT rates: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
    
    if ($e->getPrevious()) {
        echo "Original error: " . $e->getPrevious()->getMessage() . "\n";
    }
    
    exit(1);
}