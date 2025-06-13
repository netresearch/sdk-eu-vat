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
        
        // Should return only date part, no time
        $this->assertEquals('2024-01-15', $result);
    }
    
    public function testConvertPhpToXmlWithDateTimeImmutable(): void
    {
        $date = new DateTimeImmutable('2024-01-15 14:30:00');
        $result = $this->converter->convertPhpToXml($date);
        
        $this->assertEquals('2024-01-15', $result);
    }
    
    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15');
        
        $this->assertEquals('2024-01-15', $result);
    }
    
    public function testConvertPhpToXmlWithStringIncludingTime(): void
    {
        $result = $this->converter->convertPhpToXml('2024-01-15 14:30:00');
        
        // Should strip time component
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
    
    public function testConvertPhpToXmlWithInteger(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot convert integer to XML date');
        
        $this->converter->convertPhpToXml(123);
    }
    
    public function testBidirectionalConversion(): void
    {
        $originalDate = '2024-01-15';
        
        // XML -> PHP -> XML
        $phpDate = $this->converter->convertXmlToPhp($originalDate);
        $xmlDate = $this->converter->convertPhpToXml($phpDate);
        
        $this->assertEquals($originalDate, $xmlDate);
    }
}