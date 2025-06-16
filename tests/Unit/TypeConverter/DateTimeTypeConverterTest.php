<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\TypeConverter;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Netresearch\EuVatSdk\Exception\ParseException;
use Netresearch\EuVatSdk\TypeConverter\DateTimeTypeConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test DateTimeTypeConverter
 */
class DateTimeTypeConverterTest extends TestCase
{
    private DateTimeTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new DateTimeTypeConverter();
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

    public function testConvertXmlToPhpWithTimezone(): void
    {
        // Date format doesn't include time or timezone
        $result = $this->converter->convertXmlToPhp('2024-01-15');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    public function testConvertXmlToPhpWithOffset(): void
    {
        // Date format doesn't include time or timezone
        $result = $this->converter->convertXmlToPhp('2024-01-15');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
    }

    public function testConvertXmlToPhpInvalidDateTime(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse date value: not-a-date');

        $this->converter->convertXmlToPhp('not-a-date');
    }

    public function testConvertPhpToXmlWithDateTime(): void
    {
        $dateTime = new DateTime('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($dateTime);

        $this->assertEquals('2024-01-15', $result);
    }

    public function testConvertPhpToXmlWithDateTimeImmutable(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($dateTime);

        $this->assertEquals('2024-01-15', $result);
    }

    public function testConvertPhpToXmlWithUtcTimezone(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00', new DateTimeZone('UTC'));
        $result = $this->converter->convertPhpToXml($dateTime);

        $this->assertEquals('2024-01-15', $result);
    }

    public function testConvertPhpToXmlWithNonUtcTimezone(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00', new DateTimeZone('Europe/Berlin'));
        $result = $this->converter->convertPhpToXml($dateTime);

        // Date format only includes the date part
        $this->assertEquals('2024-01-15', $result);
    }

    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15');

        $this->assertEquals('2024-01-15', $result);
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

    public function testBidirectionalConversion(): void
    {
        $originalDate = '2024-01-15';

        // XML -> PHP -> XML
        $phpDateTime = $this->converter->convertXmlToPhp($originalDate);
        $xmlDate = $this->converter->convertPhpToXml($phpDateTime);

        $this->assertEquals($originalDate, $xmlDate);
    }

    public function testBidirectionalConversionWithTimezone(): void
    {
        $originalDate = '2024-01-15';

        // XML -> PHP -> XML
        $phpDateTime = $this->converter->convertXmlToPhp($originalDate);
        $xmlDate = $this->converter->convertPhpToXml($phpDateTime);

        // Should preserve the date part
        $this->assertEquals('2024-01-15', $xmlDate);
    }
}
