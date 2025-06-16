<?php

declare(strict_types=1);

/**
 * Enterprise integration example for EU VAT SDK
 *
 * This example demonstrates enterprise-grade features including
 * telemetry, monitoring, dependency injection, and production patterns.
 *
 * @package Netresearch\EuVatSdk\Examples
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\Client\{ClientConfiguration, VatRetrievalClientInterface};
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use Monolog\Logger;
use Monolog\Handler\{StreamHandler, RotatingFileHandler};
use Monolog\Processor\{PsrLogMessageProcessor, IntrospectionProcessor};

echo "=== EU VAT SDK - Enterprise Integration Example ===\n\n";

// Example 1: Custom telemetry implementation
echo "1. Implementing custom telemetry:\n";

class EnterpriseTelemetry implements TelemetryInterface
{
    private Logger $logger;
    private array $metrics = [];
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    public function recordRequest(string $operation, float $duration, array $context = []): void
    {
        // Record metrics for monitoring systems (Prometheus, DataDog, etc.)
        $this->metrics[] = [
            'type' => 'request',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => microtime(true),
            'context' => $context,
        ];
        
        // Log for observability
        $this->logger->info('VAT service request completed', [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'countries_count' => $context['countries_count'] ?? null,
            'success' => true,
        ]);
        
        // Send to monitoring system (example)
        $this->sendToMonitoring([
            'metric' => 'vat_service.request.duration',
            'value' => $duration * 1000,
            'tags' => [
                'operation' => $operation,
                'environment' => getenv('APP_ENV') ?: 'production',
            ],
        ]);
    }
    
    public function recordError(string $operation, string $errorType, array $context = []): void
    {
        $this->metrics[] = [
            'type' => 'error',
            'operation' => $operation,
            'error_type' => $errorType,
            'timestamp' => microtime(true),
            'context' => $context,
        ];
        
        $this->logger->error('VAT service error occurred', [
            'operation' => $operation,
            'error_type' => $errorType,
            'context' => $context,
        ]);
        
        // Send error metrics
        $this->sendToMonitoring([
            'metric' => 'vat_service.error.count',
            'value' => 1,
            'tags' => [
                'operation' => $operation,
                'error_type' => $errorType,
                'environment' => getenv('APP_ENV') ?: 'production',
            ],
        ]);
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
    
    private function sendToMonitoring(array $data): void
    {
        // In a real implementation, send to your monitoring system
        // Examples: StatsD, Prometheus, DataDog, New Relic, etc.
        
        // Example for StatsD:
        // $this->statsd->timing($data['metric'], $data['value'], $data['tags']);
        
        // For demonstration, just log
        $this->logger->debug('Metric sent to monitoring', $data);
    }
}

// Set up enterprise logging
$logger = new Logger('vat-enterprise');

// Console handler for development
$consoleHandler = new StreamHandler('php://stderr', Logger::INFO);
$logger->pushHandler($consoleHandler);

// Rotating file handler for production
$fileHandler = new RotatingFileHandler(
    __DIR__ . '/logs/vat-enterprise.log',
    7, // Keep 7 days
    Logger::INFO
);
$logger->pushHandler($fileHandler);

// Add processors for better context
$logger->pushProcessor(new PsrLogMessageProcessor());
$logger->pushProcessor(new IntrospectionProcessor());

$telemetry = new EnterpriseTelemetry($logger);

echo "   ✓ Custom telemetry implementation created\n";

// Example 2: Dependency injection container setup
echo "\n2. Setting up dependency injection:\n";

class VatServiceContainer
{
    private array $services = [];
    
    public function register(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }
    
    public function get(string $id)
    {
        if (!isset($this->services[$id])) {
            throw new \InvalidArgumentException("Service '$id' not found");
        }
        
        return $this->services[$id]($this);
    }
}

$container = new VatServiceContainer();

// Register logger
$container->register('logger', fn() => $logger);

// Register telemetry
$container->register('telemetry', fn($c) => new EnterpriseTelemetry($c->get('logger')));

// Register configuration
$container->register('config', function($c) {
    return ClientConfiguration::production($c->get('logger'))
        ->withTimeout(30)
        ->withSoapOptions([
            'cache_wsdl' => WSDL_CACHE_DISK,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'user_agent' => 'Enterprise-App/1.0 (+https://company.com)',
        ]);
});

// Register VAT client
$container->register('vat_client', function($c) {
    return VatRetrievalClientFactory::createWithTelemetry(
        $c->get('config'),
        $c->get('logger'),
        $c->get('telemetry')
    );
});

echo "   ✓ Dependency injection container configured\n";

// Example 3: Service wrapper with enterprise features
echo "\n3. Creating enterprise service wrapper:\n";

class EnterpriseVatService
{
    private VatRetrievalClientInterface $client;
    private TelemetryInterface $telemetry;
    private Logger $logger;
    private array $cache = [];
    
    public function __construct(
        VatRetrievalClientInterface $client,
        TelemetryInterface $telemetry,
        Logger $logger
    ) {
        $this->client = $client;
        $this->telemetry = $telemetry;
        $this->logger = $logger;
    }
    
    public function getVatRates(array $countries, \DateTimeInterface $date): array
    {
        $cacheKey = $this->getCacheKey($countries, $date);
        
        // Check cache first
        if (isset($this->cache[$cacheKey])) {
            $this->logger->debug('VAT rates retrieved from cache', [
                'countries' => $countries,
                'date' => $date->format('Y-m-d'),
            ]);
            return $this->cache[$cacheKey];
        }
        
        $startTime = microtime(true);
        
        try {
            $request = new VatRatesRequest($countries, $date);
            $response = $this->client->retrieveVatRates($request);
            
            $results = [];
            foreach ($response->getResults() as $result) {
                $results[$result->getMemberState()] = [
                    'country' => $result->getMemberState(),
                    'rate' => $result->getVatRate()->getValue(),
                    'type' => $result->getVatRate()->getType(),
                    'decimal_rate' => $result->getVatRate()->getDecimalValue(),
                    'date' => $result->getSituationOn()->format('Y-m-d'),
                ];
            }
            
            // Cache the results
            $this->cache[$cacheKey] = $results;
            
            $this->telemetry->recordRequest(
                'get_vat_rates',
                microtime(true) - $startTime,
                [
                    'countries_count' => count($countries),
                    'results_count' => count($results),
                    'cached' => false,
                ]
            );
            
            return $results;
            
        } catch (VatServiceException $e) {
            $this->telemetry->recordError(
                'get_vat_rates',
                get_class($e),
                [
                    'countries' => $countries,
                    'error_message' => $e->getMessage(),
                ]
            );
            
            throw $e;
        }
    }
    
    public function calculateVatAmount(string $netAmount, string $vatRate): array
    {
        $net = \Brick\Math\BigDecimal::of($netAmount);
        $rate = \Brick\Math\BigDecimal::of($vatRate);
        
        $vatAmount = $net->multipliedBy($rate)->dividedBy('100', 2);
        $grossAmount = $net->plus($vatAmount);
        
        return [
            'net' => $net->__toString(),
            'vat' => $vatAmount->__toString(),
            'gross' => $grossAmount->__toString(),
            'rate' => $rate->__toString(),
        ];
    }
    
    private function getCacheKey(array $countries, \DateTimeInterface $date): string
    {
        sort($countries);
        return md5(implode(',', $countries) . '|' . $date->format('Y-m-d'));
    }
    
    public function clearCache(): void
    {
        $this->cache = [];
        $this->logger->info('VAT service cache cleared');
    }
}

$vatService = new EnterpriseVatService(
    $container->get('vat_client'),
    $container->get('telemetry'),
    $container->get('logger')
);

echo "   ✓ Enterprise service wrapper created\n";

// Example 4: Health check implementation
echo "\n4. Implementing health checks:\n";

class VatServiceHealthCheck
{
    private VatRetrievalClientInterface $client;
    private Logger $logger;
    
    public function __construct(VatRetrievalClientInterface $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }
    
    public function check(): array
    {
        $healthStatus = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => [],
        ];
        
        // Check 1: Basic connectivity
        try {
            $startTime = microtime(true);
            
            $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
            $response = $this->client->retrieveVatRates($request);
            
            $duration = microtime(true) - $startTime;
            
            $healthStatus['checks']['connectivity'] = [
                'status' => 'healthy',
                'duration_ms' => round($duration * 1000, 2),
                'message' => 'Service is responsive',
            ];
            
        } catch (VatServiceException $e) {
            $healthStatus['status'] = 'unhealthy';
            $healthStatus['checks']['connectivity'] = [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'error_type' => get_class($e),
            ];
        }
        
        // Check 2: Response time
        $maxAcceptableTime = 5.0; // 5 seconds
        if (isset($healthStatus['checks']['connectivity']['duration_ms'])) {
            $responseTime = $healthStatus['checks']['connectivity']['duration_ms'] / 1000;
            
            $healthStatus['checks']['response_time'] = [
                'status' => $responseTime <= $maxAcceptableTime ? 'healthy' : 'degraded',
                'duration_s' => $responseTime,
                'threshold_s' => $maxAcceptableTime,
            ];
            
            if ($responseTime > $maxAcceptableTime) {
                $healthStatus['status'] = 'degraded';
            }
        }
        
        return $healthStatus;
    }
}

$healthCheck = new VatServiceHealthCheck($container->get('vat_client'), $logger);
$health = $healthCheck->check();

echo "   Health check status: " . $health['status'] . "\n";
foreach ($health['checks'] as $checkName => $checkResult) {
    echo "     $checkName: " . $checkResult['status'];
    if (isset($checkResult['duration_ms'])) {
        echo " ({$checkResult['duration_ms']}ms)";
    }
    echo "\n";
}

// Example 5: Circuit breaker pattern
echo "\n5. Implementing circuit breaker pattern:\n";

class VatServiceCircuitBreaker
{
    private VatRetrievalClientInterface $client;
    private Logger $logger;
    private int $failureThreshold;
    private int $timeoutSeconds;
    private int $failureCount = 0;
    private ?float $lastFailureTime = null;
    private bool $isOpen = false;
    
    public function __construct(
        VatRetrievalClientInterface $client,
        Logger $logger,
        int $failureThreshold = 5,
        int $timeoutSeconds = 60
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->failureThreshold = $failureThreshold;
        $this->timeoutSeconds = $timeoutSeconds;
    }
    
    public function call(VatRatesRequest $request)
    {
        if ($this->isOpen) {
            if ($this->shouldAttemptReset()) {
                $this->logger->info('Circuit breaker attempting reset');
                $this->isOpen = false;
            } else {
                throw new \RuntimeException('Circuit breaker is open - service unavailable');
            }
        }
        
        try {
            $response = $this->client->retrieveVatRates($request);
            $this->onSuccess();
            return $response;
            
        } catch (VatServiceException $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        if ($this->isOpen) {
            $this->logger->info('Circuit breaker closed - service recovered');
            $this->isOpen = false;
        }
    }
    
    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);
        
        if ($this->failureCount >= $this->failureThreshold && !$this->isOpen) {
            $this->isOpen = true;
            $this->logger->error('Circuit breaker opened due to failures', [
                'failure_count' => $this->failureCount,
                'threshold' => $this->failureThreshold,
            ]);
        }
    }
    
    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null &&
               (microtime(true) - $this->lastFailureTime) >= $this->timeoutSeconds;
    }
    
    public function getStatus(): array
    {
        return [
            'is_open' => $this->isOpen,
            'failure_count' => $this->failureCount,
            'last_failure_time' => $this->lastFailureTime,
        ];
    }
}

$circuitBreaker = new VatServiceCircuitBreaker($container->get('vat_client'), $logger);

echo "   ✓ Circuit breaker implemented\n";

// Example 6: Enterprise usage demonstration
echo "\n6. Demonstrating enterprise features:\n";

try {
    // Use the enterprise service
    echo "   Retrieving VAT rates with enterprise features...\n";
    
    $vatRates = $vatService->getVatRates(['DE', 'FR', 'IT'], new DateTime('2024-01-01'));
    
    echo "   ✓ Retrieved " . count($vatRates) . " VAT rates\n";
    
    // Demonstrate calculation service
    $calculation = $vatService->calculateVatAmount('1000.00', '19.0');
    echo "   VAT calculation: €{$calculation['net']} + €{$calculation['vat']} = €{$calculation['gross']}\n";
    
    // Show telemetry data
    $metrics = $telemetry->getMetrics();
    echo "   Telemetry recorded " . count($metrics) . " events\n";
    
} catch (VatServiceException $e) {
    echo "   ✗ Enterprise service error: " . $e->getMessage() . "\n";
}

// Example 7: Production monitoring endpoint
echo "\n7. Production monitoring endpoint:\n";

function createMonitoringEndpoint($vatService, $healthCheck, $telemetry): array
{
    return [
        'service' => 'eu-vat-sdk',
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'health' => $healthCheck->check(),
        'metrics' => [
            'total_requests' => count(array_filter(
                $telemetry->getMetrics(),
                fn($m) => $m['type'] === 'request'
            )),
            'total_errors' => count(array_filter(
                $telemetry->getMetrics(),
                fn($m) => $m['type'] === 'error'
            )),
        ],
        'environment' => getenv('APP_ENV') ?: 'production',
    ];
}

$monitoringData = createMonitoringEndpoint($vatService, $healthCheck, $telemetry);

echo "   Monitoring endpoint response:\n";
echo "     Service: " . $monitoringData['service'] . " v" . $monitoringData['version'] . "\n";
echo "     Health: " . $monitoringData['health']['status'] . "\n";
echo "     Requests: " . $monitoringData['metrics']['total_requests'] . "\n";
echo "     Errors: " . $monitoringData['metrics']['total_errors'] . "\n";

echo "\nEnterprise integration examples completed!\n";
echo "\nEnterprise Best Practices:\n";
echo "- Implement comprehensive telemetry and monitoring\n";
echo "- Use dependency injection for testability\n";
echo "- Add health checks for service discovery\n";
echo "- Implement circuit breakers for resilience\n";
echo "- Use structured logging for observability\n";
echo "- Cache responses appropriately\n";
echo "- Monitor error rates and response times\n";
echo "- Implement graceful degradation patterns\n";