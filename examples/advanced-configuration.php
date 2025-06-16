<?php

declare(strict_types=1);

/**
 * Advanced configuration example for EU VAT SDK
 *
 * This example demonstrates how to create custom configurations
 * with logging, timeouts, debugging, and monitoring.
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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

echo "=== EU VAT SDK - Advanced Configuration Example ===\n\n";

// Example 1: Custom logging configuration
echo "1. Setting up custom logging:\n";

$logger = new Logger('vat-service');

// Add console handler for development
$consoleHandler = new StreamHandler('php://stderr', Logger::DEBUG);
$consoleHandler->setFormatter(new LineFormatter(
    "[%datetime%] %level_name%: %message% %context%\n",
    'Y-m-d H:i:s'
));
$logger->pushHandler($consoleHandler);

// Add rotating file handler for production
$fileHandler = new RotatingFileHandler(
    __DIR__ . '/logs/vat-service.log',
    0, // Keep all files
    Logger::INFO
);
$logger->pushHandler($fileHandler);

echo "   ✓ Logger configured with console and file handlers\n";

// Example 2: Production configuration
echo "\n2. Creating production configuration:\n";

$productionConfig = ClientConfiguration::production($logger)
    ->withTimeout(30)                           // 30 second timeout
    ->withDebug(false)                          // Disable debug mode
    ->withSoapOptions([
        'cache_wsdl' => WSDL_CACHE_DISK,        // Cache WSDL on disk
        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        'connection_timeout' => 30,              // Connection timeout
        'user_agent' => 'EuVatSdk/1.0 (Company Name)',
    ]);

echo "   ✓ Production configuration created\n";

// Example 3: Development/testing configuration
echo "\n3. Creating development configuration:\n";

$devConfig = ClientConfiguration::test($logger)
    ->withTimeout(60)                           // Longer timeout for debugging
    ->withDebug(true)                           // Enable debug mode
    ->withSoapOptions([
        'cache_wsdl' => WSDL_CACHE_NONE,        // No caching for development
        'trace' => true,                        // Enable SOAP tracing
        'exceptions' => true,                   // Throw exceptions on SOAP faults
    ]);

echo "   ✓ Development configuration created\n";

// Example 4: Using the configured client
echo "\n4. Using the configured client:\n";

try {
    // Create client with production config
    $client = VatRetrievalClientFactory::create($productionConfig);
    
    $request = new VatRatesRequest(
        memberStates: ['DE', 'FR'],
        situationOn: new DateTime('2024-01-01')
    );

    echo "   Requesting VAT rates with production config...\n";
    $response = $client->retrieveVatRates($request);
    
    foreach ($response->getResults() as $result) {
        printf(
            "   %s: %s%% (logged to file)\n",
            $result->getMemberState(),
            $result->getVatRate()->getValue()
        );
    }

} catch (VatServiceException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Example 5: Custom SOAP options
echo "\n5. Custom SOAP configuration:\n";

$customConfig = ClientConfiguration::production($logger)
    ->withSoapOptions([
        // Performance optimizations
        'cache_wsdl' => WSDL_CACHE_DISK,
        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        
        // Timeouts
        'connection_timeout' => 30,
        'default_socket_timeout' => 60,
        
        // Headers and identification
        'user_agent' => 'MyApp/2.0 (https://example.com)',
        
        // SSL options (for custom certificates if needed)
        'stream_context' => stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'cafile' => '/path/to/custom/ca-bundle.crt', // Optional
            ],
            'http' => [
                'timeout' => 30,
            ],
        ]),
        
        // SOAP specific
        'soap_version' => SOAP_1_1,
        'encoding' => 'UTF-8',
        'exceptions' => true,
        'trace' => false, // Disable in production
    ]);

echo "   ✓ Custom SOAP options configured\n";

// Example 6: Environment-based configuration
echo "\n6. Environment-based configuration:\n";

$environment = getenv('APP_ENV') ?: 'production';

switch ($environment) {
    case 'development':
    case 'dev':
        $config = ClientConfiguration::test($logger)
            ->withDebug(true)
            ->withTimeout(60);
        echo "   ✓ Using development configuration\n";
        break;
        
    case 'testing':
    case 'test':
        $config = ClientConfiguration::test($logger)
            ->withDebug(false)
            ->withTimeout(30);
        echo "   ✓ Using testing configuration\n";
        break;
        
    case 'staging':
        $config = ClientConfiguration::production($logger)
            ->withDebug(true)
            ->withTimeout(30);
        echo "   ✓ Using staging configuration\n";
        break;
        
    case 'production':
    case 'prod':
    default:
        $config = ClientConfiguration::production($logger)
            ->withDebug(false)
            ->withTimeout(30);
        echo "   ✓ Using production configuration\n";
        break;
}

// Example 7: Configuration validation
echo "\n7. Configuration validation:\n";

try {
    // This would throw ConfigurationException if invalid
    $validatedConfig = ClientConfiguration::production($logger)
        ->withTimeout(30)
        ->withSoapOptions([
            'cache_wsdl' => WSDL_CACHE_DISK,
        ]);
    
    echo "   ✓ Configuration validated successfully\n";
    
    // Test the configuration
    $testClient = VatRetrievalClientFactory::create($validatedConfig);
    echo "   ✓ Client created successfully with validated config\n";
    
} catch (Exception $e) {
    echo "   ✗ Configuration validation failed: " . $e->getMessage() . "\n";
}

// Example 8: Logging and monitoring via middleware
echo "\n8. Logging and monitoring via middleware:\n";

// Custom logger with structured logging
$structuredLogger = new Logger('vat-service');
$structuredLogger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

try {
    // Configure client with logging middleware
    $middlewareConfig = ClientConfiguration::production($structuredLogger)
        ->withDebug(true); // This enables the built-in logging middleware
    
    $monitoredClient = VatRetrievalClientFactory::create($middlewareConfig);
    
    $request = new VatRatesRequest(
        memberStates: ['DE'],
        situationOn: new DateTime('2024-01-01')
    );
    
    // The LoggingMiddleware will automatically log request/response details
    $response = $monitoredClient->retrieveVatRates($request);
    
    echo "   ✓ Request and response were logged automatically by middleware\n";
    echo "   ✓ Check logs for detailed request/response information\n";
    
} catch (VatServiceException $e) {
    // The middleware will also log errors automatically
    echo "   ✗ Error logged automatically by middleware: " . $e->getMessage() . "\n";
}

echo "\nAdvanced configuration examples completed!\n";
echo "\nTips:\n";
echo "- Use production() config for live environments\n";
echo "- Use test() config for development and testing\n";
echo "- Enable debug mode only when needed (impacts performance)\n";
echo "- Configure appropriate timeouts for your use case\n";
echo "- Use structured logging for better monitoring\n";