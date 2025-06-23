<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\DTO\Response;

use Brick\Math\BigDecimal;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use Netresearch\EuVatSdk\Exception\ParseException;
use PHPUnit\Framework\TestCase;

/**
 * Test VatRate DTO
 */
class VatRateTest extends TestCase
{
    public function testStandardRate(): void
    {
        $rate = new VatRate('STANDARD', '19.0');

        $this->assertEquals('STANDARD', $rate->getType());
        $this->assertEquals('19.0', $rate->getValue());
        $this->assertInstanceOf(BigDecimal::class, $rate->getDecimalValue());
        $this->assertEquals('19.0', $rate->getDecimalValue()->__toString());
        $this->assertEquals(19.0, $rate->getValueAsFloat());
        $this->assertNull($rate->getCategory());

        $this->assertTrue($rate->isStandard());
        $this->assertFalse($rate->isReduced());
        $this->assertFalse($rate->isSuperReduced());
        $this->assertFalse($rate->isParkingRate());
        $this->assertFalse($rate->isZeroRate());
        $this->assertFalse($rate->isExempt());
    }

    public function testReducedRate(): void
    {
        $rate = new VatRate('REDUCED', '7.0', 'FOODSTUFFS');

        $this->assertEquals('REDUCED', $rate->getType());
        $this->assertEquals('7.0', $rate->getValue());
        $this->assertEquals('FOODSTUFFS', $rate->getCategory());

        $this->assertFalse($rate->isStandard());
        $this->assertTrue($rate->isReduced());
    }

    public function testReducedIndexedRate(): void
    {
        $rate = new VatRate('REDUCED[1]', '5.5');

        $this->assertEquals('REDUCED[1]', $rate->getType());
        $this->assertTrue($rate->isReduced());
    }

    public function testSuperReducedRate(): void
    {
        $rate = new VatRate('SUPER_REDUCED', '2.1');

        $this->assertTrue($rate->isSuperReduced());
        $this->assertFalse($rate->isReduced());
    }

    public function testParkingRates(): void
    {
        $rate1 = new VatRate('PK', '12.0');
        $this->assertTrue($rate1->isParkingRate());

        $rate2 = new VatRate('PARKING', '12.0');
        $this->assertTrue($rate2->isParkingRate());
    }

    public function testZeroRates(): void
    {
        $rate1 = new VatRate('Z', '0.0');
        $this->assertTrue($rate1->isZeroRate());

        $rate2 = new VatRate('ZERO', '0.0');
        $this->assertTrue($rate2->isZeroRate());
    }

    public function testExemptRates(): void
    {
        $rate1 = new VatRate('E', '0.0');
        $this->assertTrue($rate1->isExempt());

        $rate2 = new VatRate('EXEMPT', '0.0');
        $this->assertTrue($rate2->isExempt());
    }

    public function testNormalizesTypeToUppercase(): void
    {
        $rate = new VatRate('standard', '19.0');
        $this->assertEquals('STANDARD', $rate->getType());
        $this->assertTrue($rate->isStandard());
    }

    public function testTrimsType(): void
    {
        $rate = new VatRate('  STANDARD  ', '19.0');
        $this->assertEquals('STANDARD', $rate->getType());
    }

    public function testThrowsOnInvalidDecimalValue(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Failed to parse decimal value: not-a-number');

        $rate = new VatRate('STANDARD', 'not-a-number');
        $rate->getValue(); // Trigger lazy initialization
    }

    public function testPreciseDecimalHandling(): void
    {
        $rate = new VatRate('STANDARD', '19.75');

        $this->assertEquals('19.75', $rate->getValue());
        $this->assertEquals('19.75', $rate->getDecimalValue()->__toString());

        // Test calculation precision
        $amount = BigDecimal::of('100.00');
        $vatAmount = $amount->multipliedBy($rate->getDecimalValue())->dividedBy(100, 2);
        $this->assertEquals('19.75', $vatAmount->__toString());
    }

    public function testToString(): void
    {
        $rate = new VatRate('STANDARD', '19.0');

        $this->assertEquals('19.0', (string) $rate);
        $this->assertEquals($rate->getValue(), (string) $rate);
    }
}
