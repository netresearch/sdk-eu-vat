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

    public function testGetTypeName(): void
    {
        $this->assertEquals('decimal', $this->converter->getTypeName());
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

    public function testConvertXmlToPhpInvalidDecimal(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse decimal value: not-a-number (invalid number format)');

        $this->converter->convertXmlToPhp('not-a-number');
    }

    public function testConvertXmlToPhpValueTooHigh(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('VAT rate value too high: 150.0 (maximum allowed: 100%)');

        $this->converter->convertXmlToPhp('150.0');
    }

    public function testConvertXmlToPhpValueTooLow(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('VAT rate value too low: -20.0 (minimum allowed: -10%)');

        $this->converter->convertXmlToPhp('-20.0');
    }

    public function testConvertXmlToPhpBoundaryValues(): void
    {
        // Test maximum allowed value
        $maxResult = $this->converter->convertXmlToPhp('100.0');
        $this->assertTrue($maxResult->isEqualTo(BigDecimal::of('100.0')));

        // Test minimum allowed value
        $minResult = $this->converter->convertXmlToPhp('-10.0');
        $this->assertTrue($minResult->isEqualTo(BigDecimal::of('-10.0')));
    }

    public function testConvertPhpToXmlWithBigDecimal(): void
    {
        $bigDecimal = BigDecimal::of('19.75');
        $result = $this->converter->convertPhpToXml($bigDecimal);

        $this->assertEquals('19.75', $result);
    }

    public function testConvertPhpToXmlWithFloat(): void
    {
        $result = $this->converter->convertPhpToXml(19.75);

        $this->assertEquals('19.75', $result);
    }

    public function testConvertPhpToXmlWithInteger(): void
    {
        $result = $this->converter->convertPhpToXml(25);

        $this->assertEquals('25', $result);
    }

    public function testConvertPhpToXmlWithString(): void
    {
        $result = $this->converter->convertPhpToXml('19.75');

        $this->assertEquals('19.75', $result);
    }

    public function testConvertPhpToXmlWithZero(): void
    {
        $result = $this->converter->convertPhpToXml(0);

        $this->assertEquals('0', $result);
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

    public function testConvertPhpToXmlValueTooHigh(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('VAT rate value too high: 150.5 (maximum allowed: 100%)');

        $this->converter->convertPhpToXml(150.5);
    }

    public function testConvertPhpToXmlValueTooLow(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('VAT rate value too low: -15 (minimum allowed: -10%)');

        $this->converter->convertPhpToXml(-15.0);
    }

    public function testBidirectionalConversion(): void
    {
        $originalDecimal = '19.75';

        // XML -> PHP -> XML
        $phpDecimal = $this->converter->convertXmlToPhp($originalDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals($originalDecimal, $xmlDecimal);
    }

    public function testBidirectionalConversionWithInteger(): void
    {
        $originalDecimal = '25';

        // XML -> PHP -> XML
        $phpDecimal = $this->converter->convertXmlToPhp($originalDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals($originalDecimal, $xmlDecimal);
    }

    public function testPrecisionPreservation(): void
    {
        // Test that decimal precision is preserved
        $preciseDecimal = '19.123456789';
        $phpDecimal = $this->converter->convertXmlToPhp($preciseDecimal);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        $this->assertEquals($preciseDecimal, $xmlDecimal);
    }

    public function testTrailingZerosPreservation(): void
    {
        // Test that trailing zeros in decimals are preserved
        $decimalWithZeros = '19.50';
        $phpDecimal = $this->converter->convertXmlToPhp($decimalWithZeros);
        $xmlDecimal = $this->converter->convertPhpToXml($phpDecimal);

        // BigDecimal preserves trailing zeros in the string representation
        $this->assertEquals($decimalWithZeros, $xmlDecimal);
    }
}
