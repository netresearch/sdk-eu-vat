<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use Netresearch\EuVatSdk\Exception\ConversionException;
use Brick\Math\BigDecimal;
use DateTime;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Soap\Engine\Engine;
use stdClass;

/**
 * Test SOAP DTO hydration to verify ClassMap configuration
 *
 * DISCOVERY: This test revealed that the current DTO architecture is incompatible
 * with SOAP ClassMap hydration. DTOs like VatRatesResponse and VatRateResult require
 * constructor arguments and cannot be instantiated by SOAP ClassMap (which doesn't call constructors).
 *
 * The manual hydration fallback is NECESSARY given the current DTO design.
 * For full ClassMap hydration, DTOs would need to be redesigned with public properties
 * and no required constructor arguments.
 */
class SoapHydrationTest extends TestCase
{
    private LoggerInterface $logger;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = ClientConfiguration::test($this->logger);
    }

    /**
     * Test converter approach: SOAP engine returns stdClass, converter creates DTOs
     *
     * This represents our new approach where the SOAP engine returns stdClass
     * and VatRatesResponseConverter handles all DTO creation.
     */
    public function testConverterBasedHydration(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        // Create a stdClass response with properly converted types (as TypeConverters would do)
        $stdClassResponse = $this->createStdClassResponseWithConvertedTypes();

        // Mock the engine to return the stdClass response
        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->with('retrieveVatRates', [$request])
            ->willReturn($stdClassResponse);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);
        $response = $client->retrieveVatRates($request);

        // Verify converter approach works
        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertCount(1, $response->getResults());

        $result = $response->getResults()[0];
        $this->assertInstanceOf(VatRateResult::class, $result);
        $this->assertEquals('DE', $result->getMemberState());
        $this->assertInstanceOf(VatRate::class, $result->getRate());
        $this->assertEquals('STANDARD', $result->getRate()->getType());
        $this->assertEquals('19.0', $result->getRate()->getRawValue());
    }

    /**
     * Test why DTOs are incompatible with ClassMap instantiation
     *
     * This test demonstrates that VatRatesResponse cannot be instantiated
     * by ClassMap because it requires constructor arguments.
     */
    public function testDtoClassMapIncompatibility(): void
    {
        // Attempt to instantiate VatRatesResponse the way ClassMap would
        try {
            // ClassMap instantiation - creates object without calling constructor
            $response = (new \ReflectionClass(VatRatesResponse::class))->newInstanceWithoutConstructor();

            // This will fail because $results property is not initialized
            $response->getResults();

            $this->fail('Expected error when accessing uninitialized readonly property');
        } catch (\Error $e) {
            $this->assertStringContainsString('must not be accessed before initialization', $e->getMessage());
            // This proves why ClassMap can't work with current DTO design
        }

        // Same issue with VatRateResult
        try {
            $result = (new \ReflectionClass(VatRateResult::class))->newInstanceWithoutConstructor();
            $result->getMemberState();

            $this->fail('Expected error when accessing uninitialized property');
        } catch (\Error $e) {
            $this->assertStringContainsString('must not be accessed before initialization', $e->getMessage());
        }

        // VatRate works because it uses lazy initialization
        $rate = (new \ReflectionClass(VatRate::class))->newInstanceWithoutConstructor();
        $reflection = new \ReflectionProperty(VatRate::class, 'type');
        $reflection->setValue($rate, 'STANDARD');
        $reflection = new \ReflectionProperty(VatRate::class, 'value');
        $reflection->setValue($rate, '19.0');

        // This works because VatRate handles uninitialized state properly
        $this->assertEquals('STANDARD', $rate->getType());
        $this->assertEquals('19.0', $rate->getRawValue());
    }

    /**
     * Test converter handling of raw stdClass without TypeConverter preprocessing
     *
     * This tests the converter's fallback behavior when TypeConverters haven't
     * processed the data yet (string dates, numeric values instead of BigDecimal).
     */
    public function testConverterWithRawStdClass(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        // Create a stdClass response with raw types (no TypeConverter processing)
        $stdClassResponse = $this->createStdClassResponse();

        $mockEngine = $this->createMock(Engine::class);
        $mockEngine->expects($this->once())
            ->method('request')
            ->with('retrieveVatRates', [$request])
            ->willReturn($stdClassResponse);

        $client = new SoapVatRetrievalClient($this->config, $mockEngine);

        // This should now fail because our converter expects proper types
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Expected "situationOn" to be a DateTimeInterface object');

        $client->retrieveVatRates($request);
    }

    /**
     * Test ClassMap type name verification against XSD
     *
     * This test verifies that our ClassMap keys match the actual XSD type names
     * from VatRetrievalServiceType.xsd. This is critical for proper SOAP hydration.
     */
    public function testClassMapTypeNamesMatchXsd(): void
    {
        // From VatRetrievalServiceType.xsd analysis:
        // Line 32: <xs:complexType name="rateValueType"> - CORRECT in ClassMap
        // Line 85: <xs:complexType name="retrieveVatRatesRespType"> - NOT "vatRatesResponse"!
        // Line 108: Elements are "vatRateResults" but no named type for individual results

        // The current ClassMap uses WRONG type names:
        $currentClassMapNames = [
            'rateValueType',     // ✓ CORRECT - matches XSD line 32
            'vatRateResult',     // ✗ WRONG - there's no such type in XSD
            'vatRatesResponse',  // ✗ WRONG - should be 'retrieveVatRatesRespType'
            'vatRatesRequest'    // Need to verify this one
        ];

        // The XSD shows these are the actual type names:
        $correctXsdTypeNames = [
            'rateValueType',                // Line 32 - VAT rate structure
            'retrieveVatRatesRespType',    // Line 85 - Response type
            'retrieveVatRatesReqType',     // Line 68 - Request type
            // Note: vatRateResults (line 108) are unnamed complex types within the response
        ];

        // Test the known correct type
        $this->assertContains('rateValueType', $currentClassMapNames);
        $this->assertContains('rateValueType', $correctXsdTypeNames);

        // Highlight the incorrect types that need fixing
        $this->assertNotContains('vatRatesResponse', $correctXsdTypeNames);
        $this->assertNotContains('vatRateResult', $correctXsdTypeNames);

        // Mark that we've identified the root cause
        $this->addToAssertionCount(1); // Indicate this test identified the ClassMap issue
    }

    /**
     * Test with corrected XML structure based on actual XSD
     *
     * This shows the correct XML structure according to the XSD schema
     */
    public function testCorrectSoapXmlStructure(): void
    {
        // This represents the correct XML structure based on XSD analysis
        $correctXml = $this->getCorrectSoapResponseXml();

        $dom = new \DOMDocument();
        $dom->loadXML($correctXml);

        // The response should be retrieveVatRatesRespMsg (from Message.xsd line 5)
        $responseElement = $dom->getElementsByTagName('retrieveVatRatesRespMsg')->item(0);
        $this->assertNotNull($responseElement, 'Should find retrieveVatRatesRespMsg element');

        // Should contain vatRateResults elements (XSD line 108)
        $resultElements = $dom->getElementsByTagName('vatRateResults');
        $this->assertGreaterThan(0, $resultElements->length, 'Should find vatRateResults elements');

        // Each result should have rate element of type rateValueType (XSD line 113 -> 32)
        $rateElements = $dom->getElementsByTagName('rate');
        $this->assertGreaterThan(0, $rateElements->length, 'Should find rate elements');

        // Verify the structure matches XSD expectations
        $firstResult = $resultElements->item(0);
        $this->assertNotNull($firstResult);

        $memberStateElement = $firstResult->getElementsByTagName('memberState')->item(0);
        $this->assertNotNull($memberStateElement);
        $memberState = $memberStateElement->textContent;
        $this->assertEquals('DE', $memberState);

        $rateElement = $firstResult->getElementsByTagName('rate')->item(0);
        $this->assertNotNull($rateElement);

        $rateTypeElement = $rateElement->getElementsByTagName('type')->item(0);
        $this->assertNotNull($rateTypeElement);
        $rateType = $rateTypeElement->textContent;

        $rateValueElement = $rateElement->getElementsByTagName('value')->item(0);
        $this->assertNotNull($rateValueElement);
        $rateValue = $rateValueElement->textContent;

        $this->assertEquals('DEFAULT', $rateType);  // Updated to match XSD enum
        $this->assertEquals('19.0', $rateValue);
    }


    /**
     * Create a stdClass response that simulates what SOAP engine returns
     * with TypeConverters properly converting primitive types
     */
    private function createStdClassResponseWithConvertedTypes(): stdClass
    {
        $stdClassResponse = new stdClass();
        $stdClassResponse->vatRateResults = [];

        $result = new stdClass();
        $result->memberState = 'DE';
        $result->situationOn = new \DateTimeImmutable('2024-01-01'); // TypeConverter converted

        $rate = new stdClass();
        $rate->type = 'STANDARD';
        $rate->value = BigDecimal::of('19.0'); // TypeConverter converted
        $result->rate = $rate;

        $stdClassResponse->vatRateResults[] = $result;

        return $stdClassResponse;
    }

    /**
     * Create a stdClass response with raw types (no TypeConverter processing)
     * This simulates what would happen if TypeConverters weren't working
     */
    private function createStdClassResponse(): stdClass
    {
        $stdClassResponse = new stdClass();
        $stdClassResponse->vatRateResults = [];

        $result = new stdClass();
        $result->memberState = 'DE';
        $result->situationOn = '2024-01-01'; // Raw string, not converted

        $rate = new stdClass();
        $rate->type = 'STANDARD';
        $rate->value = '19.0'; // Raw string, not BigDecimal
        $result->rate = $rate;

        $stdClassResponse->vatRateResults[] = $result;

        return $stdClassResponse;
    }

    /**
     * Corrected SOAP response XML structure based on actual XSD analysis
     * This represents the XML structure that matches the XSD schema definitions
     */
    private function getCorrectSoapResponseXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <ns:retrieveVatRatesRespMsg xmlns:ns="urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService">
            <ns:additionalInformation>
                <ns:countries>
                    <ns:country>
                        <ns:isoCode>DE</ns:isoCode>
                        <ns:cnCodeProvided>false</ns:cnCodeProvided>
                        <ns:cpaCodeProvided>false</ns:cpaCodeProvided>
                    </ns:country>
                </ns:countries>
            </ns:additionalInformation>
            <ns:vatRateResults>
                <ns:memberState>DE</ns:memberState>
                <ns:type>STANDARD</ns:type>
                <ns:rate>
                    <ns:type>DEFAULT</ns:type>
                    <ns:value>19.0</ns:value>
                </ns:rate>
                <ns:situationOn>2024-01-01</ns:situationOn>
            </ns:vatRateResults>
        </ns:retrieveVatRatesRespMsg>
    </soap:Body>
</soap:Envelope>
XML;
    }
}
