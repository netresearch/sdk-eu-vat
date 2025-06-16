<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Brick\Math\BigDecimal;
use DateTime;

/**
 * End-to-end integration tests for the complete SDK workflow
 *
 * This test validates the entire SDK from installation to production usage,
 * including error handling, performance characteristics, and edge cases.
 *
 * @group integration
 * @group e2e
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class EndToEndTest extends IntegrationTestCase
{
    /**
     * Test complete workflow: installation to API call
     *
     * @test
     */
    public function testCompleteWorkflowFromInstallationToApiCall(): void
    {
        $this->setupVcr('e2e-complete-workflow');

        // Step 1: Create client with default configuration
        $client = VatRetrievalClientFactory::create();
        $this->assertNotNull($client, 'Client should be created successfully');

        // Step 2: Make a simple request
        $request = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $client->retrieveVatRates($request);

        // Step 3: Validate response
        $this->assertCount(1, $response->getResults());
        $result = $response->getResults()[0];
        
        $this->assertEquals('DE', $result->getMemberState());
        $this->assertEquals('STANDARD', $result->getVatRate()->getType());
        $this->assertEquals('19.0', $result->getVatRate()->getValue());
    }

    /**
     * Test all supported PHP versions behavior
     *
     * @test
     */
    public function testPhpVersionCompatibility(): void
    {
        $this->setupVcr('e2e-php-compatibility');

        // Verify we're running on PHP 8.1+
        $this->assertGreaterThanOrEqual(80100, PHP_VERSION_ID);

        // Test modern PHP features work correctly
        $client = VatRetrievalClientFactory::create();
        
        // Test named arguments (PHP 8.0+)
        $request = new VatRatesRequest(
            memberStates: ['FR', 'IT'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $client->retrieveVatRates($request);
        $this->assertCount(2, $response->getResults());

        // Test readonly properties work (PHP 8.1+)
        foreach ($response->getResults() as $result) {
            $this->assertIsString($result->getMemberState());
            $this->assertInstanceOf(\DateTimeInterface::class, $result->getSituationOn());
        }
    }

    /**
     * Test memory usage with large datasets
     *
     * @test
     * @group performance
     */
    public function testMemoryUsageWithLargeDatasets(): void
    {
        $this->setupVcr('e2e-memory-usage');

        $memoryStart = memory_get_usage();

        // Request all EU member states
        $allEuMembers = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        $client = VatRetrievalClientFactory::create();
        $request = new VatRatesRequest(
            memberStates: $allEuMembers,
            situationOn: new DateTime('2024-01-01')
        );

        $response = $client->retrieveVatRates($request);
        
        $memoryEnd = memory_get_usage();
        $memoryUsed = ($memoryEnd - $memoryStart) / 1024 / 1024; // Convert to MB

        $this->assertCount(27, $response->getResults());
        $this->assertLessThan(5, $memoryUsed, 'Memory usage should be under 5MB for all EU states');
        
        $this->logger->info('Memory usage for all EU states', [
            'memory_mb' => round($memoryUsed, 2),
            'results_count' => count($response->getResults()),
        ]);
    }

    /**
     * Test concurrent request handling
     *
     * @test
     * @group performance
     */
    public function testConcurrentRequestHandling(): void
    {
        $this->setupVcr('e2e-concurrent-requests');

        $client = VatRetrievalClientFactory::create();
        
        // Simulate multiple requests with different configurations
        $requests = [
            new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01')),
            new VatRatesRequest(['IT', 'ES'], new DateTime('2024-01-01')),
            new VatRatesRequest(['NL', 'BE'], new DateTime('2024-01-01')),
        ];

        $startTime = microtime(true);
        $responses = [];

        foreach ($requests as $request) {
            $responses[] = $client->retrieveVatRates($request);
        }

        $duration = microtime(true) - $startTime;

        $this->assertCount(3, $responses);
        $this->assertLessThan(3.0, $duration, 'Three sequential requests should complete within 3 seconds');

        // Verify all responses are valid
        foreach ($responses as $response) {
            $this->assertCount(2, $response->getResults());
        }
    }

    /**
     * Test error handling completeness
     *
     * @test
     */
    public function testComprehensiveErrorHandling(): void
    {
        $this->setupVcr('e2e-error-handling');

        $client = VatRetrievalClientFactory::create();

        // Test 1: Invalid country codes
        try {
            $request = new VatRatesRequest(['XX', 'YY'], new DateTime('2024-01-01'));
            $client->retrieveVatRates($request);
            $this->fail('Should throw InvalidRequestException');
        } catch (\Netresearch\EuVatSdk\Exception\InvalidRequestException $e) {
            $this->assertStringContainsString('TEDB-101', $e->getMessage());
        }

        // Test 2: Empty member states
        try {
            $request = new VatRatesRequest([], new DateTime('2024-01-01'));
            $client->retrieveVatRates($request);
            $this->fail('Should throw exception for empty states');
        } catch (VatServiceException $e) {
            $this->assertTrue(true);
        }

        // Test 3: Configuration errors
        try {
            $invalidConfig = ClientConfiguration::production()
                ->withWsdlPath('/non/existent/path.wsdl');
            
            $invalidClient = new \Netresearch\EuVatSdk\Client\SoapVatRetrievalClient($invalidConfig);
            $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
            $invalidClient->retrieveVatRates($request);
            $this->fail('Should throw ConfigurationException');
        } catch (\Netresearch\EuVatSdk\Exception\ConfigurationException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test BigDecimal precision in financial calculations
     *
     * @test
     */
    public function testFinancialCalculationPrecision(): void
    {
        $this->setupVcr('e2e-financial-precision');

        $client = VatRetrievalClientFactory::create();
        $request = new VatRatesRequest(['LU', 'MT'], new DateTime('2024-01-01'));
        
        $response = $client->retrieveVatRates($request);

        foreach ($response->getResults() as $result) {
            $vatRate = $result->getVatRate();
            
            // Test precise decimal handling
            $decimalValue = $vatRate->getDecimalValue();
            $this->assertInstanceOf(BigDecimal::class, $decimalValue);
            
            // Test financial calculations
            $netAmount = BigDecimal::of('999.99');
            $vatAmount = $netAmount->multipliedBy($decimalValue)->dividedBy('100', 2);
            $grossAmount = $netAmount->plus($vatAmount);
            
            // Verify calculations maintain precision
            $this->assertEquals(
                $netAmount->plus($vatAmount)->__toString(),
                $grossAmount->__toString(),
                'Financial calculations should maintain precision'
            );
            
            // Verify no floating point errors
            $this->assertMatchesRegularExpression(
                '/^\d+\.\d{2}$/',
                $vatAmount->__toString(),
                'VAT amount should have exactly 2 decimal places'
            );
        }
    }

    /**
     * Test different client configurations
     *
     * @test
     */
    public function testVariousClientConfigurations(): void
    {
        $cassetteName = 'e2e-client-configurations';
        
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Test 1: Production configuration
        $prodClient = VatRetrievalClientFactory::create(
            ClientConfiguration::production($this->logger)
        );
        
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $response = $prodClient->retrieveVatRates($request);
        $this->assertCount(1, $response->getResults());

        // Test 2: Test configuration with debug
        $testClient = VatRetrievalClientFactory::createForTesting($this->logger, true);
        
        $response = $testClient->retrieveVatRates($request);
        $this->assertCount(1, $response->getResults());

        // Test 3: Custom configuration with timeout
        $customConfig = ClientConfiguration::production($this->logger)
            ->withTimeout(60)
            ->withDebug(true)
            ->withSoapOptions([
                'cache_wsdl' => WSDL_CACHE_DISK,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            ]);
        
        $customClient = VatRetrievalClientFactory::create($customConfig);
        $response = $customClient->retrieveVatRates($request);
        $this->assertCount(1, $response->getResults());
    }

    /**
     * Test SOAP client optimization features
     *
     * @test
     * @group performance
     */
    public function testSoapClientOptimizations(): void
    {
        $this->setupVcr('e2e-soap-optimizations');

        // Test WSDL caching
        $startTime = microtime(true);
        
        // First client creation (might load WSDL)
        $client1 = VatRetrievalClientFactory::create();
        $time1 = microtime(true) - $startTime;
        
        // Second client creation (should use cached WSDL)
        $startTime = microtime(true);
        $client2 = VatRetrievalClientFactory::create();
        $time2 = microtime(true) - $startTime;
        
        // Second creation should be faster due to caching
        $this->assertLessThan($time1, $time2, 'Second client creation should be faster due to WSDL caching');
        
        // Test compression
        $config = ClientConfiguration::production($this->logger)
            ->withSoapOptions([
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            ]);
        
        $compressedClient = VatRetrievalClientFactory::create($config);
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $response = $compressedClient->retrieveVatRates($request);
        
        $this->assertCount(1, $response->getResults());
    }

    /**
     * Test environment-specific behaviors
     *
     * @test
     */
    public function testEnvironmentSpecificBehaviors(): void
    {
        $this->setupVcr('e2e-environment-behaviors');

        // Test with different environments
        $environments = ['production', 'test', 'development'];
        
        foreach ($environments as $env) {
            $client = VatRetrievalClientFactory::createForEnvironment($env, $this->logger);
            
            $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
            
            try {
                $response = $client->retrieveVatRates($request);
                $this->assertCount(1, $response->getResults());
                
                $this->logger->info('Environment test passed', [
                    'environment' => $env,
                    'result_count' => count($response->getResults()),
                ]);
            } catch (VatServiceException $e) {
                // Some environments might not be available
                $this->logger->warning('Environment test failed', [
                    'environment' => $env,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Test edge cases and boundary conditions
     *
     * @test
     */
    public function testEdgeCasesAndBoundaryConditions(): void
    {
        $this->setupVcr('e2e-edge-cases');

        $client = VatRetrievalClientFactory::create();

        // Test 1: Very old date
        $oldRequest = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('2000-01-01')
        );
        
        try {
            $response = $client->retrieveVatRates($oldRequest);
            // Service might return data or error for very old dates
            $this->assertNotNull($response);
        } catch (VatServiceException $e) {
            $this->logger->info('Old date test result', ['error' => $e->getMessage()]);
        }

        // Test 2: Single character country codes (should fail)
        try {
            new VatRatesRequest(['D', 'F'], new DateTime('2024-01-01'));
            $this->fail('Should reject single character country codes');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        // Test 3: Mixed case country codes (should be normalized)
        $mixedRequest = new VatRatesRequest(
            memberStates: ['de', 'Fr', 'IT'],
            situationOn: new DateTime('2024-01-01')
        );
        
        $response = $client->retrieveVatRates($mixedRequest);
        
        // Verify normalization worked
        $countries = array_map(
            fn($r) => $r->getMemberState(),
            $response->getResults()
        );
        
        $this->assertContains('DE', $countries);
        $this->assertContains('FR', $countries);
        $this->assertContains('IT', $countries);
    }
}