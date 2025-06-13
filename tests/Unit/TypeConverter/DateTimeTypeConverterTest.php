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
        $this->assertEquals('dateTime', $this->converter->getTypeName());
    }
    
    public function testConvertXmlToPhp(): void
    {
        $result = $this->converter->convertXmlToPhp('2024-01-15T14:30:00');
        
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
        $this->assertEquals('14:30:00', $result->format('H:i:s'));
    }
    
    public function testConvertXmlToPhpWithTimezone(): void
    {
        $result = $this->converter->convertXmlToPhp('2024-01-15T14:30:00Z');
        
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
        $this->assertEquals('14:30:00', $result->format('H:i:s'));
    }
    
    public function testConvertXmlToPhpWithOffset(): void
    {
        $result = $this->converter->convertXmlToPhp('2024-01-15T14:30:00+02:00');
        
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        // The exact time will depend on timezone handling
        $this->assertEquals('2024-01-15', $result->format('Y-m-d'));
    }
    
    public function testConvertXmlToPhpInvalidDateTime(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse dateTime value: not-a-datetime');
        
        $this->converter->convertXmlToPhp('not-a-datetime');
    }
    
    public function testConvertPhpToXmlWithDateTime(): void
    {
        $dateTime = new DateTime('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($dateTime);
        
        $this->assertEquals('2024-01-15T14:30:00', $result);
    }
    
    public function testConvertPhpToXmlWithDateTimeImmutable(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($dateTime);
        
        $this->assertEquals('2024-01-15T14:30:00', $result);
    }
    
    public function testConvertPhpToXmlWithUtcTimezone(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00', new DateTimeZone('UTC'));
        $result = $this->converter->convertPhpToXml($dateTime);
        
        $this->assertEquals('2024-01-15T14:30:00', $result);
    }
    
    public function testConvertPhpToXmlWithNonUtcTimezone(): void
    {
        $dateTime = new DateTimeImmutable('2024-01-15 14:30:00', new DateTimeZone('Europe/Berlin'));
        $result = $this->converter->convertPhpToXml($dateTime);
        
        // Should convert to UTC and add Z suffix
        $this->assertStringEndsWith('Z', $result);
        $this->assertStringStartsWith('2024-01-15T', $result);
    }
    
    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15T14:30:00');
        
        $this->assertEquals('2024-01-15T14:30:00', $result);
    }
    
    public function testConvertPhpToXmlInvalidString(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse dateTime string: not-a-datetime');
        
        $this->converter->convertPhpToXml('not-a-datetime');
    }
    
    public function testConvertPhpToXmlInvalidType(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert stdClass to XML dateTime');
        
        $this->converter->convertPhpToXml(new \stdClass());
    }
    
    public function testBidirectionalConversion(): void
    {
        $originalDateTime = '2024-01-15T14:30:00';
        
        // XML -> PHP -> XML
        $phpDateTime = $this->converter->convertXmlToPhp($originalDateTime);
        $xmlDateTime = $this->converter->convertPhpToXml($phpDateTime);
        
        $this->assertEquals($originalDateTime, $xmlDateTime);
    }
    
    public function testBidirectionalConversionWithTimezone(): void
    {
        $originalDateTime = '2024-01-15T14:30:00Z';
        
        // XML -> PHP -> XML
        $phpDateTime = $this->converter->convertXmlToPhp($originalDateTime);
        $xmlDateTime = $this->converter->convertPhpToXml($phpDateTime);
        
        // Should preserve the essence, though format might slightly differ
        $this->assertStringContainsString('2024-01-15T14:30:00', $xmlDateTime);
    }
}