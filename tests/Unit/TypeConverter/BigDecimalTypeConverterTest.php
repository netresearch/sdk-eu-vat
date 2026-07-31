<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\TypeConverter;

use Brick\Math\BigDecimal;
use Netresearch\EuVatSdk\Exception\ParseException;
use Netresearch\EuVatSdk\TypeConverter\BigDecimalTypeConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test BigDecimalTypeConverter
 */
class BigDecimalTypeConverterTest extends TestCase
{
    private BigDecimalTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new BigDecimalTypeConverter();
    }

    public function testGetTypeNamespace(): void
    {
        $this->assertEquals('http://www.w3.org/2001/XMLSchema', $this->converter->getTypeNamespace());
    }

    /**
     * The TEDB schema declares rate values as xs:double and uses xs:decimal
     * nowhere, so 'decimal' would register a typemap entry that never matches.
     */
    public function testGetTypeName(): void
    {
        $this->assertEquals('double', $this->converter->getTypeName());
    }

    public function testConvertXmlToPhp(): void
    {
        $result = $this->converter->convertXmlToPhp('19.75');

        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertTrue($result->isEqualTo(BigDecimal::of('19.75')));
        $this->assertEquals('19.75', $result->__toString());
    }

    public function testConvertXmlToPhpWithInteger(): void
    {
        $result = $this->converter->convertXmlToPhp('25');

        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertTrue($result->isEqualTo(BigDecimal::of('25')));
        $this->assertEquals('25', $result->__toString());
    }

    public function testConvertXmlToPhpWithZero(): void
    {
        $result = $this->converter->convertXmlToPhp('0.0');

        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertTrue($result->isEqualTo(BigDecimal::of('0.0')));
        $this->assertEquals('0.0', $result->__toString());
    }

    /**
     * ext-soap hands the complete serialized element to from_xml callbacks.
     */
    public function testConvertXmlToPhpWithNamespacedElement(): void
    {
        $result = $this->converter->convertXmlToPhp(
            '<value xmlns="urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService:types">17.0</value>'
        );

        $this->assertInstanceOf(BigDecimal::class, $result);
        $this->assertSame('17.0', $result->__toString());
        $this->assertSame(1, $result->getScale());
    }

    public function testConvertXmlToPhpPreservesScaleFromWire(): void
    {
        $this->assertSame('17.0', (string) $this->converter->convertXmlToPhp('<value>17.0</value>'));
        $this->assertSame('17.00', (string) $this->converter->convertXmlToPhp('<value>17.00</value>'));
        $this->assertSame('17', (string) $this->converter->convertXmlToPhp('<value>17</value>'));
    }

    public function testConvertXmlToPhpWithEmptyElementReturnsNull(): void
    {
        $this->assertNull($this->converter->convertXmlToPhp('<value/>'));
        $this->assertNull($this->converter->convertXmlToPhp('<value></value>'));
    }

    public function testConvertXmlToPhpInvalidDecimal(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse decimal value: not-a-number (invalid number format)');

        $this->converter->convertXmlToPhp('not-a-number');
    }

    /**
     * Regression guard for a removed VAT-plausibility check: this converter is a
     * generic xsd:double converter and must not abort deserialization of an entire
     * SOAP response because a single rate value looks implausible. Domain-level
     * plausibility is the caller's concern.
     */
    public function testConvertXmlToPhpDoesNotRejectOutOfRangeValues(): void
    {
        $this->assertSame('150.0', (string) $this->converter->convertXmlToPhp('<value>150.0</value>'));
        $this->assertSame('-20.0', (string) $this->converter->convertXmlToPhp('<value>-20.0</value>'));
    }

    public function testConvertPhpToXmlWithBigDecimal(): void
    {
        $bigDecimal = BigDecimal::of('19.75');
        $result = $this->converter->convertPhpToXml($bigDecimal);

        $this->assertEquals('<double>19.75</double>', $result);
    }

    public function testConvertPhpToXmlWithFloat(): void
    {
        $result = $this->converter->convertPhpToXml(19.75);

        $this->assertEquals('<double>19.75</double>', $result);
    }

    public function testConvertPhpToXmlWithInteger(): void
    {
        $result = $this->converter->convertPhpToXml(25);

        $this->assertEquals('<double>25</double>', $result);
    }

    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('19.75');

        $this->assertEquals('<double>19.75</double>', $result);
    }

    public function testConvertPhpToXmlWithZero(): void
    {
        $result = $this->converter->convertPhpToXml(0);

        $this->assertEquals('<double>0</double>', $result);
    }

    public function testConvertPhpToXmlInvalidString(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse decimal string: not-a-number');

        $this->converter->convertPhpToXml('not-a-number');
    }

    public function testConvertPhpToXmlInvalidType(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert stdClass to XML decimal');

        $this->converter->convertPhpToXml(new \stdClass());
    }

    public function testConvertPhpToXmlArray(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert array to XML decimal');

        $this->converter->convertPhpToXml([1, 2, 3]);
    }

    public function testConvertPhpToXmlDoesNotRejectOutOfRangeValues(): void
    {
        $this->assertSame('<double>150.5</double>', $this->converter->convertPhpToXml(150.5));
        $this->assertSame('<double>-15</double>', $this->converter->convertPhpToXml(-15.0));
    }

    public function testBidirectionalConversion(): void
    {
        $originalDecimal = '19.75';

        // XML -> PHP -> XML
        $phpDecimal = $this->converter->convertXmlToPhp($originalDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals('<double>' . $originalDecimal . '</double>', $xmlDecimal);

        // The produced element must round-trip back through convertXmlToPhp()
        $this->assertSame($originalDecimal, (string) $this->converter->convertXmlToPhp($xmlDecimal));
    }

    public function testBidirectionalConversionWithInteger(): void
    {
        $originalDecimal = '25';

        // XML -> PHP -> XML
        $phpDecimal = $this->converter->convertXmlToPhp($originalDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals('<double>' . $originalDecimal . '</double>', $xmlDecimal);
    }

    public function testPrecisionPreservation(): void
    {
        // Test that decimal precision is preserved
        $preciseDecimal = '19.123456789';
        $phpDecimal = $this->converter->convertXmlToPhp($preciseDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals('<double>' . $preciseDecimal . '</double>', $xmlDecimal);
    }

    public function testTrailingZerosPreservation(): void
    {
        // Test that trailing zeros in decimals are preserved
        $decimalWithZeros = '19.50';
        $phpDecimal = $this->converter->convertXmlToPhp($decimalWithZeros);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        // BigDecimal preserves trailing zeros in the string representation
        $this->assertEquals('<double>' . $decimalWithZeros . '</double>', $xmlDecimal);
    }

    /**
     * Empirical proof that the typemap entry actually fires.
     *
     * Drives a real ext-soap decode of a RECORDED response body through the
     * bundled WSDL, with only this converter's own type_ns/type_name registered.
     * With the registration reverted to 'decimal' the typemap matches nothing,
     * ext-soap decodes <value>17.0</value> into the PHP float 17.0 and both the
     * instanceof and the '17.0' assertion below fail (the float stringifies as
     * "17"). This test is therefore a direct guard on the registration.
     */
    public function testTypemapDecodesRecordedRateValueAsScaledBigDecimal(): void
    {
        $root = dirname(__DIR__, 3);
        $cassette = json_decode(
            (string) file_get_contents($root . '/tests/fixtures/cassettes/vat-rates-decimal-precision'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertIsArray($cassette);
        $this->assertIsArray($cassette[0]);
        $this->assertIsArray($cassette[0]['response']);
        $recordedBody = $cassette[0]['response']['body'];
        $this->assertIsString($recordedBody);

        $converter = $this->converter;
        $client = new class ($root . '/resources/VatRetrievalService.wsdl', [
            'typemap' => [[
                'type_ns' => $converter->getTypeNamespace(),
                'type_name' => $converter->getTypeName(),
                'from_xml' => $converter->convertXmlToPhp(...),
            ]],
            'location' => 'http://localhost/unused',
        ]) extends \SoapClient {
            public string $recordedResponse = '';

            // php-vcr rewrites `extends \SoapClient` to extend its own SoapClient, which
            // from 1.8.2 declares this extra optional parameter. Accepting it keeps the
            // override valid against that parent and against plain \SoapClient alike.
            public function __doRequest(
                string $request,
                string $location,
                string $action,
                int $version,
                bool $oneWay = false,
                ?string $uriParserClass = null
            ): string {
                return $this->recordedResponse;
            }
        };
        $client->recordedResponse = $recordedBody;

        $response = $client->__soapCall('retrieveVatRates', [[
            'memberStates' => ['isoCode' => 'LU'],
            'situationOn' => '2024-01-01',
        ]]);

        $standardRate = null;
        foreach ((array) $response->vatRateResults as $result) {
            if ($result->memberState === 'LU' && $result->rate->type === 'DEFAULT') {
                $standardRate = $result->rate->value;
                break;
            }
        }

        $this->assertInstanceOf(
            BigDecimal::class,
            $standardRate,
            'ext-soap must hand the rate value to this converter; a float here means the typemap did not fire.'
        );
        $this->assertSame('17.0', $standardRate->__toString(), 'The scale sent on the wire must survive decoding.');
        $this->assertSame(1, $standardRate->getScale());
    }
}
