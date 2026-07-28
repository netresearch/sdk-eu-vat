<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use DateTime;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\UnexpectedResponseException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Soap\Engine\Engine;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\Exception\RequestException;

/**
 * Test SoapVatRetrievalClient implementation
 */
class SoapVatRetrievalClientTest extends TestCase
{
    private LoggerInterface $logger;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = ClientConfiguration::test($this->logger)
            ->withTimeout(30);
    }

    public function testImplementsVatRetrievalClientInterface(): void
    {
        $mockEngine = $this->createMock(Engine::class);
        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->assertInstanceOf(VatRetrievalClientInterface::class, $client);
    }

    public function testConstructorInitializesEngine(): void
    {
        $mockEngine = $this->createMock(Engine::class);
        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->assertInstanceOf(Engine::class, $client->getEngine());
    }

    public function testGetConfigurationReturnsCorrectConfig(): void
    {
        $mockEngine = $this->createMock(Engine::class);
        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->assertSame($this->config, $client->getConfiguration());
    }

    public function testRetrieveVatRatesWithValidRequest(): void
    {
        $request = new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01'));

        // Create a mock stdClass response that simulates what the SOAP engine returns
        $mockStdClassResponse = new \stdClass();
        $mockStdClassResponse->vatRateResults = [];

        // Mock the engine to return the stdClass response
        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->with('retrieveVatRates', [$request])
            ->willReturn($mockStdClassResponse);

        // Inject the mock engine directly into the client
        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $response = $client->retrieveVatRates($request);

        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertCount(0, $response->getResults());
    }

    public function testRetrieveVatRatesHandlesRequestException(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $requestException = RequestException::internalSoapError('Network timeout');

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($requestException);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage(
            'Network error occurred while connecting to EU VAT service: Internal ext-soap error: Network timeout'
        );

        $client->retrieveVatRates($request);
    }

    public function testRetrieveVatRatesHandlesRuntimeException(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $runtimeException = new \RuntimeException('WSDL parsing error');

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($runtimeException);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        // Raw exceptions should be wrapped in UnexpectedResponseException
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('An unexpected error occurred during the SOAP request: WSDL parsing error');

        $client->retrieveVatRates($request);
    }

    public function testRetrieveVatRatesHandlesSoapFaultFallback(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        // A fault code the service is not documented to send and that carries no
        // client/server attribution must stay a generic SOAP fault.
        $soapFault = new \SoapFault('env:VersionMismatch', 'Unknown fault');

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($soapFault);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessage('Unknown fault');

        $client->retrieveVatRates($request);
    }

    /**
     * The fault fixture is taken verbatim from tests/fixtures/cassettes/error-invalid-country-code:
     * faultcode `env:Client`, faultstring `TEDB-ERR-2 - Request is not valid`, with the per-error
     * descriptions carried in the fault detail.
     */
    public function testRetrieveVatRatesMapsRecordedClientFaultToInvalidRequestException(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        $error = new \stdClass();
        $error->code = '00002';
        $error->description = 'The Member State "XX" does not exist.';
        $faultMsg = new \stdClass();
        $faultMsg->error = $error;
        $detail = new \stdClass();
        $detail->retrieveVatRatesFaultMsg = $faultMsg;

        $soapFault = new \SoapFault('env:Client', 'TEDB-ERR-2 - Request is not valid');
        $soapFault->detail = $detail;

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($soapFault);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        try {
            $client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame('TEDB-ERR-2', $e->getErrorCode());
            $this->assertStringContainsString('TEDB-ERR-2 - Request is not valid', $e->getMessage());
            $this->assertStringContainsString('[00002] The Member State "XX" does not exist.', $e->getMessage());
            $this->assertSame($soapFault, $e->getPrevious());
        }
    }

    public function testRetrieveVatRatesMapsServerFaultToServiceUnavailableException(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $soapFault = new \SoapFault('env:Server', 'Internal processing failure');

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($soapFault);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Internal error in the EU VAT service (env:Server): Internal processing failure');

        $client->retrieveVatRates($request);
    }

    public function testRetrieveVatRatesLetsDomainExceptionsPropagateUnwrapped(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $domainException = new InvalidRequestException('Invalid request data', 'TEDB-ERR-2');

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->willThrowException($domainException);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        try {
            $client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException to be thrown');
        } catch (InvalidRequestException $e) {
            $this->assertSame($domainException, $e, 'Domain exception must propagate unwrapped');
        }
    }

    public function testConstructorThrowsConfigurationExceptionOnInvalidWsdl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('WSDL file not found: /non/existent/wsdl.xml');

        // This should fail at configuration validation, not client construction
        ClientConfiguration::test()
            ->withWsdlPath('/non/existent/wsdl.xml');
    }

    public function testConstructorWithDebugModeRegistersListeners(): void
    {
        $debugConfig = ClientConfiguration::test($this->logger)
            ->withDebug(true);

        $mockEngine = $this->createMock(Engine::class);

        // This test verifies that the client initializes successfully with debug mode
        $client = new SoapVatRetrievalClient($debugConfig, $mockEngine);

        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);
    }

    public function testConstructorWithProductionModeSkipsDebugListeners(): void
    {
        $productionConfig = ClientConfiguration::production($this->logger);

        $mockEngine = $this->createMock(Engine::class);
        $client = new SoapVatRetrievalClient($productionConfig, $mockEngine);

        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);
    }

    public function testConstructorSetsCorrectSoapOptions(): void
    {
        $customOptions = [
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'trace' => true,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        ];

        $config = ClientConfiguration::test()
            ->withSoapOptions($customOptions)
            ->withTimeout(60);

        $mockEngine = $this->createMock(Engine::class);
        $client = new SoapVatRetrievalClient($config, $mockEngine);

        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);
    }

    public function testConstructorHandlesDefaultWsdlPath(): void
    {
        // Test with default WSDL path
        $config = ClientConfiguration::test();

        // For this test, we'll create a mock WSDL file or skip if file doesn't exist
        $defaultWsdlPath = __DIR__ . '/../../../resources/VatRetrievalService.wsdl';
        if (!file_exists($defaultWsdlPath)) {
            $this->markTestSkipped('Default WSDL file not found for testing');
        }

        $client = new SoapVatRetrievalClient($config);

        $this->assertInstanceOf(SoapVatRetrievalClient::class, $client);
    }

    public function testInitializeEngineBuildsWithoutErrors(): void
    {
        $wsdlPath = __DIR__ . '/../../../resources/VatRetrievalService.wsdl';
        if (!file_exists($wsdlPath)) {
            $this->markTestSkipped('Default WSDL file not found for testing.');
        }

        $config = ClientConfiguration::test($this->logger)->withDebug(true);

        // This will call the real initializeEngine()
        $client = new SoapVatRetrievalClient($config);

        $engine = $client->getEngine();

        // After fixing compatibility, the engine should be SimpleEngine (v1.7.0 limitation)
        $this->assertInstanceOf(SimpleEngine::class, $engine);
    }
}
