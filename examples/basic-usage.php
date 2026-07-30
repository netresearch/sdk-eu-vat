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
use Netresearch\EuVatSdk\DTO\Response\VatRate;

/**
 * Format a VAT rate for display.
 *
 * Rate types such as exempt or out-of-scope carry no percentage at all:
 * VatRate::getValue() returns null and casting the rate to a string yields an
 * empty string. Render those explicitly instead of printing a bare "%".
 *
 * @param VatRate $rate The rate to render.
 */
function formatRate(VatRate $rate): string
{
    return $rate->getValue() === null ? 'n/a' : (string) $rate . '%';
}

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
            "   %s: %s (%s rate)\n",
            $result->getMemberState(),
            formatRate($result->getRate()),
            $result->getRate()->getType()
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
            "   %s: %s (%s)\n",
            $result->getMemberState(),
            formatRate($result->getRate()),
            $result->getRate()->getType()
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
            "   %s (2020): %s (%s)\n",
            $result->getMemberState(),
            formatRate($result->getRate()),
            $result->getRate()->getType()
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

    // Find one specific row. The service returns one row per rate type (STANDARD,
    // REDUCED, PARKING_RATE, ...) per member state, so the rate type has to be
    // selected explicitly - taking the first row for a country would print whichever
    // rate type happened to come first.
    foreach ($results as $result) {
        if ($result->getMemberState() === 'DE' && $result->getRate()->isStandard()) {
            $vatRate = $result->getRate();

            echo "   Germany, standard rate:\n";
            echo "     - Country: " . $result->getMemberState() . "\n";
            echo "     - Type: " . $vatRate->getType() . "\n";
            echo "     - Date: " . $result->getSituationOn()->format('Y-m-d') . "\n";

            // Get precise decimal value for calculations. Rate types without a
            // percentage (exempt, out of scope, ...) return null here, so always
            // check before using the value.
            $decimalRate = $vatRate->getValue();
            if ($decimalRate === null) {
                echo "     - Rate: none (no percentage for this rate type)\n";
            } else {
                echo "     - Rate: " . (string) $vatRate . "%\n";
                echo "     - Decimal rate: " . $decimalRate->__toString() . "\n";
            }

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