<?php

declare(strict_types=1);

/**
 * Error handling example for EU VAT SDK
 *
 * This example demonstrates comprehensive error handling patterns
 * and how to properly catch and handle different types of exceptions.
 *
 * @package Netresearch\EuVatSdk\Examples
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\{
    VatServiceException,
    InvalidRequestException,
    ServiceUnavailableException,
    ConfigurationException,
    SoapFaultException,
    ValidationException,
    ParseException,
    UnexpectedResponseException
};
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

echo "=== EU VAT SDK - Error Handling Example ===\n\n";

// Set up logging for error tracking
$logger = new Logger('vat-error-demo');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

$client = VatRetrievalClientFactory::create(
    ClientConfiguration::test($logger)->withDebug(true)
);

// Example 1: Invalid country code handling
echo "1. Testing invalid country code handling:\n";

try {
    $request = new VatRatesRequest(
        memberStates: ['XX', 'YY'], // Invalid country codes
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    echo "   Unexpected success - this should have failed!\n";
    
} catch (InvalidRequestException $e) {
    echo "   ✓ Caught InvalidRequestException as expected\n";
    echo "     Message: " . $e->getMessage() . "\n";
    echo "     Code: " . $e->getCode() . "\n";
    
    // Log the error appropriately
    $logger->warning('Invalid country codes provided', [
        'countries' => ['XX', 'YY'],
        'error' => $e->getMessage(),
    ]);
    
} catch (VatServiceException $e) {
    echo "   Caught unexpected VatServiceException: " . $e->getMessage() . "\n";
}

// Example 2: Empty member states array
echo "\n2. Testing empty member states handling:\n";

try {
    $request = new VatRatesRequest(
        memberStates: [], // Empty array
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    echo "   Unexpected success - this should have failed!\n";
    
} catch (InvalidRequestException $e) {
    echo "   ✓ Caught InvalidRequestException for empty array\n";
    echo "     Message: " . $e->getMessage() . "\n";
    
} catch (ValidationException $e) {
    echo "   ✓ Caught ValidationException for empty array\n";
    echo "     Message: " . $e->getMessage() . "\n";
}

// Example 3: Non-EU country codes
echo "\n3. Testing non-EU country codes:\n";

try {
    $request = new VatRatesRequest(
        memberStates: ['US', 'CN', 'JP'], // Non-EU countries
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    echo "   Unexpected success - this should have failed!\n";
    
} catch (InvalidRequestException $e) {
    echo "   ✓ Caught InvalidRequestException for non-EU countries\n";
    echo "     Message: " . $e->getMessage() . "\n";
    
    // Handle gracefully - maybe suggest alternatives
    echo "     Suggestion: Use valid EU member state codes (DE, FR, IT, etc.)\n";
}

// Example 4: Brexit transition handling
echo "\n4. Testing Brexit transition (UK after leaving EU):\n";

try {
    $request = new VatRatesRequest(
        memberStates: ['GB'], // UK after Brexit
        situationOn: new DateTime('2022-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    echo "   Unexpected success - UK should not be valid after Brexit\n";
    
} catch (InvalidRequestException $e) {
    echo "   ✓ Caught InvalidRequestException for post-Brexit UK\n";
    echo "     Message: " . $e->getMessage() . "\n";
    echo "     Note: UK was valid before 2021-01-01\n";
}

// Example 5: Future date handling
echo "\n5. Testing future date handling:\n";

try {
    $futureDate = new DateTime('+10 years');
    
    $request = new VatRatesRequest(
        memberStates: ['DE'],
        situationOn: $futureDate
    );
    
    $response = $client->retrieveVatRates($request);
    
    if (count($response->getResults()) > 0) {
        echo "   Service returned results for future date (behavior may vary)\n";
    } else {
        echo "   Service returned empty results for future date\n";
    }
    
} catch (InvalidRequestException $e) {
    echo "   ✓ Service rejected future date\n";
    echo "     Message: " . $e->getMessage() . "\n";
}

// Example 6: Configuration error handling
echo "\n6. Testing configuration errors:\n";

try {
    // Try to create config with invalid settings
    $invalidConfig = ClientConfiguration::production($logger)
        ->withTimeout(-1); // Invalid timeout
    
    $invalidClient = VatRetrievalClientFactory::create($invalidConfig);
    echo "   Configuration validation should have failed\n";
    
} catch (ConfigurationException $e) {
    echo "   ✓ Caught ConfigurationException\n";
    echo "     Message: " . $e->getMessage() . "\n";
    
} catch (\InvalidArgumentException $e) {
    echo "   ✓ Caught InvalidArgumentException for invalid config\n";
    echo "     Message: " . $e->getMessage() . "\n";
}

// Example 7: Service unavailable handling
echo "\n7. Testing service unavailable scenarios:\n";

try {
    // Create client with very short timeout to simulate network issues
    $timeoutConfig = ClientConfiguration::test($logger)
        ->withTimeout(1) // Very short timeout
        ->withSoapOptions([
            'connection_timeout' => 1,
            'default_socket_timeout' => 1,
        ]);
    
    $timeoutClient = VatRetrievalClientFactory::create($timeoutConfig);
    
    $request = new VatRatesRequest(
        memberStates: ['DE'],
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $timeoutClient->retrieveVatRates($request);
    echo "   Request completed within short timeout\n";
    
} catch (ServiceUnavailableException $e) {
    echo "   ✓ Caught ServiceUnavailableException (timeout/network)\n";
    echo "     Message: " . $e->getMessage() . "\n";
    
    // Implement retry logic or fallback
    echo "     Implementing retry logic...\n";
    
    try {
        // Retry with normal client
        $retryResponse = $client->retrieveVatRates($request);
        echo "     ✓ Retry successful\n";
        
    } catch (VatServiceException $retryError) {
        echo "     ✗ Retry also failed: " . $retryError->getMessage() . "\n";
    }
}

// Example 8: Comprehensive error handling function
echo "\n8. Comprehensive error handling function:\n";

function handleVatServiceError(VatServiceException $e, Logger $logger): void
{
    $errorContext = [
        'error_type' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'timestamp' => date('c'),
    ];
    
    if ($e->getPrevious()) {
        $errorContext['original_error'] = $e->getPrevious()->getMessage();
    }
    
    switch (true) {
        case $e instanceof InvalidRequestException:
            echo "   📋 Invalid Request: " . $e->getMessage() . "\n";
            echo "      Action: Check your country codes and date format\n";
            $logger->warning('Invalid request parameters', $errorContext);
            break;
            
        case $e instanceof ServiceUnavailableException:
            echo "   🔌 Service Unavailable: " . $e->getMessage() . "\n";
            echo "      Action: Retry later or check network connectivity\n";
            $logger->error('VAT service unavailable', $errorContext);
            break;
            
        case $e instanceof ConfigurationException:
            echo "   ⚙️  Configuration Error: " . $e->getMessage() . "\n";
            echo "      Action: Check your SDK configuration\n";
            $logger->critical('SDK configuration error', $errorContext);
            break;
            
        case $e instanceof SoapFaultException:
            echo "   🧼 SOAP Fault: " . $e->getMessage() . "\n";
            echo "      Action: Check service status or contact support\n";
            $logger->error('SOAP fault occurred', $errorContext);
            break;
            
        case $e instanceof ParseException:
            echo "   📄 Parse Error: " . $e->getMessage() . "\n";
            echo "      Action: Check response format or report bug\n";
            $logger->error('Response parsing failed', $errorContext);
            break;
            
        default:
            echo "   ❓ Unexpected Error: " . $e->getMessage() . "\n";
            echo "      Action: Report this issue\n";
            $logger->critical('Unexpected VAT service error', $errorContext);
            break;
    }
}

// Test the comprehensive error handler
try {
    $request = new VatRatesRequest(
        memberStates: ['INVALID'],
        situationOn: new DateTime('2024-01-01')
    );
    
    $response = $client->retrieveVatRates($request);
    
} catch (VatServiceException $e) {
    handleVatServiceError($e, $logger);
}

// Example 9: Error recovery patterns
echo "\n9. Error recovery patterns:\n";

function retrieveVatRatesWithRetry(
    $client,
    VatRatesRequest $request,
    int $maxRetries = 3,
    int $delaySeconds = 1
): array {
    $lastException = null;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            echo "   Attempt $attempt of $maxRetries...\n";
            $response = $client->retrieveVatRates($request);
            echo "   ✓ Success on attempt $attempt\n";
            return $response->getResults();
            
        } catch (ServiceUnavailableException $e) {
            $lastException = $e;
            echo "   ⚠️  Attempt $attempt failed (service unavailable)\n";
            
            if ($attempt < $maxRetries) {
                echo "   Waiting {$delaySeconds}s before retry...\n";
                sleep($delaySeconds);
                $delaySeconds *= 2; // Exponential backoff
            }
            
        } catch (InvalidRequestException $e) {
            // Don't retry for client errors
            echo "   ✗ Client error - not retrying: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    echo "   ✗ All retry attempts failed\n";
    throw $lastException;
}

// Test retry logic with valid request
try {
    $request = new VatRatesRequest(
        memberStates: ['DE'],
        situationOn: new DateTime('2024-01-01')
    );
    
    $results = retrieveVatRatesWithRetry($client, $request);
    echo "   Retrieved " . count($results) . " results with retry logic\n";
    
} catch (VatServiceException $e) {
    echo "   Retry logic failed: " . $e->getMessage() . "\n";
}

echo "\nError handling examples completed!\n";
echo "\nBest Practices:\n";
echo "- Always catch specific exception types first\n";
echo "- Log errors with appropriate context\n";
echo "- Implement retry logic for service unavailable errors\n";
echo "- Don't retry client-side validation errors\n";
echo "- Provide meaningful error messages to users\n";
echo "- Monitor error rates and patterns\n";