<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\TypeConverter;

use DateTime;
use DateTimeImmutable;
use Netresearch\EuVatSdk\Exception\ParseException;
use Netresearch\EuVatSdk\TypeConverter\DateTypeConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test DateTypeConverter
 */
class DateTypeConverterTest extends TestCase
{
    private DateTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new DateTypeConverter();
    }

    public function testGetTypeNamespace(): void
    {
        $this->assertEquals('http://www.w3.org/2001/XMLSchema', $this->converter->getTypeNamespace());
    }

    public function testGetTypeName(): void
    {
        $this->assertEquals('date', $this->converter->getTypeName());
    }

    public function testConvertXmlToPhp(): void
    {
        $result = $this->converter->convertXmlToPhp('2024-01-15');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testConvertXmlToPhpWithTimeComponent(): void
    {
        // Should strip time component
        $result = $this->converter->convertXmlToPhp('2024-01-15T14:30:00');

        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testConvertXmlToPhpInvalidDate(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse date value: not-a-date');

        $this->converter->convertXmlToPhp('not-a-date');
    }

    public function testConvertPhpToXmlWithDateTime(): void
    {
        $date = new DateTime('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($date);

        // Should return a complete XML element containing only the date part, no time
        $this->assertEquals('<date>2024-01-15</date>', $result);
    }

    public function testConvertPhpToXmlWithDateTimeImmutable(): void
    {
        $date = new DateTimeImmutable('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($date);

        $this->assertEquals('<date>2024-01-15</date>', $result);
    }

    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15');

        $this->assertEquals('<date>2024-01-15</date>', $result);
    }

    public function testConvertPhpToXmlWithStringIncludingTime(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15 14:30:00');

        // Should strip time component
        $this->assertEquals('<date>2024-01-15</date>', $result);
    }

    /**
     * Regression test: ext-soap typemap to_xml callbacks must return a complete
     * XML element. A bare date string caused ext-soap to serialize an EMPTY
     * <situationOn/> element, silently dropping the historical date from requests.
     */
    public function testTypemapEncodingPlacesDateInsideSituationOnElement(): void
    {
        $converter = $this->converter;
        $wsdl = dirname(__DIR__, 3) . '/resources/VatRetrievalService.wsdl';

        $client = new class ($wsdl, [
            'typemap' => [[
                'type_ns' => $converter->getTypeNamespace(),
                'type_name' => $converter->getTypeName(),
                'to_xml' => static fn ($php): string => $converter->convertPhpToXml($php),
            ]],
            'location' => 'http://localhost/unused',
        ]) extends \SoapClient {
            public string $capturedRequest = '';

            public function __doRequest(
                string $request,
                string $location,
                string $action,
                int $version,
                bool $oneWay = false
            ): ?string {
                $this->capturedRequest = $request;
                return '';
            }
        };

        try {
            $client->__soapCall('retrieveVatRates', [[
                'memberStates' => ['isoCode' => 'DE'],
                'situationOn' => new DateTimeImmutable('2024-01-15'),
            ]]);
        } catch (\Throwable) {
            // The empty response cannot be decoded - only the encoded request matters here.
        }

        // Would be "<ns1:situationOn/>" with a bare-string to_xml return value
        $this->assertStringNotContainsString('<ns1:situationOn/>', $client->capturedRequest);
        $this->assertStringContainsString('<ns1:situationOn>2024-01-15</ns1:situationOn>', $client->capturedRequest);
    }

    public function testConvertPhpToXmlInvalidString(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse date string: not-a-date');

        $this->converter->convertPhpToXml('not-a-date');
    }

    public function testConvertPhpToXmlInvalidType(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert stdClass to XML date');

        $this->converter->convertPhpToXml(new \stdClass());
    }

    public function testConvertPhpToXmlWithInteger(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert int to XML date');

        $this->converter->convertPhpToXml(123);
    }

    public function testBidirectionalConversion(): void
    {
        $originalDate = '2024-01-15';

        // XML -> PHP -> XML
        $phpDate = $this->converter->convertXmlToPhp($originalDate);
        $xmlDate = $this->converter->convertPhpToXml($phpDate);

        $this->assertEquals('<date>' . $originalDate . '</date>', $xmlDate);

        // The produced element must round-trip back through convertXmlToPhp()
        $roundTripped = $this->converter->convertXmlToPhp($xmlDate);
        $this->assertEquals($originalDate, $roundTripped->format('Y-m-d'));
    }
}
