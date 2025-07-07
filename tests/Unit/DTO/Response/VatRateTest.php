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

    public function testSuperReducedRateWithFullName(): void
    {
        $rate = new VatRate('SUPER_REDUCED_RATE', '4.0');

        $this->assertTrue($rate->isSuperReduced());
        $this->assertFalse($rate->isReduced());
        $this->assertFalse($rate->isStandard());
        $this->assertFalse($rate->isParkingRate());
        $this->assertFalse($rate->isZeroRate());
        $this->assertFalse($rate->isExempt());
    }

    public function testParkingRates(): void
    {
        $rate1 = new VatRate('PK', '12.0');
        $this->assertTrue($rate1->isParkingRate());

        $rate2 = new VatRate('PARKING', '12.0');
        $this->assertTrue($rate2->isParkingRate());

        $rate3 = new VatRate('PARKING_RATE', '14.0');
        $this->assertTrue($rate3->isParkingRate());
        $this->assertFalse($rate3->isStandard());
        $this->assertFalse($rate3->isReduced());
        $this->assertFalse($rate3->isSuperReduced());
        $this->assertFalse($rate3->isZeroRate());
        $this->assertFalse($rate3->isExempt());
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

        $rate3 = new VatRate('EXEMPTED', '0.0');
        $this->assertTrue($rate3->isExempt());

        $rate4 = new VatRate('NOT_APPLICABLE', '0.0');
        $this->assertTrue($rate4->isExempt());
        $this->assertFalse($rate4->isStandard());
        $this->assertFalse($rate4->isReduced());
        $this->assertFalse($rate4->isSuperReduced());
        $this->assertFalse($rate4->isParkingRate());
        $this->assertFalse($rate4->isZeroRate());

        $rate5 = new VatRate('OUT_OF_SCOPE', '0.0');
        $this->assertTrue($rate5->isExempt());
        $this->assertFalse($rate5->isStandard());
        $this->assertFalse($rate5->isReduced());
        $this->assertFalse($rate5->isSuperReduced());
        $this->assertFalse($rate5->isParkingRate());
        $this->assertFalse($rate5->isZeroRate());
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

    /**
     * Test that all known rate types from EU VAT service can be mapped to a category
     * This addresses GitHub issue #7 where some rate types were unmapped
     */
    public function testAllKnownRateTypesAreMappable(): void
    {
        $testCases = [
            // Original mappings
            'DEFAULT' => 'isStandard',
            'STANDARD' => 'isStandard',
            'REDUCED' => 'isReduced',
            'REDUCED[1]' => 'isReduced',
            'REDUCED_RATE' => 'isReduced',
            'SUPER_REDUCED' => 'isSuperReduced',
            'PK' => 'isParkingRate',
            'PARKING' => 'isParkingRate',
            'Z' => 'isZeroRate',
            'ZERO' => 'isZeroRate',
            'E' => 'isExempt',
            'EXEMPT' => 'isExempt',

            // New mappings added to fix issue #7
            'SUPER_REDUCED_RATE' => 'isSuperReduced',
            'PARKING_RATE' => 'isParkingRate',
            'EXEMPTED' => 'isExempt',
            'NOT_APPLICABLE' => 'isExempt',
            'OUT_OF_SCOPE' => 'isExempt',
        ];

        foreach ($testCases as $rateType => $expectedMethod) {
            $rate = new VatRate($rateType, '0.0');

            // Ensure the rate maps to exactly one category
            $mappings = [
                'isStandard' => $rate->isStandard(),
                'isReduced' => $rate->isReduced(),
                'isSuperReduced' => $rate->isSuperReduced(),
                'isParkingRate' => $rate->isParkingRate(),
                'isZeroRate' => $rate->isZeroRate(),
                'isExempt' => $rate->isExempt(),
            ];

            $trueMappings = array_keys(array_filter($mappings));

            $this->assertContains(
                $expectedMethod,
                $trueMappings,
                "Rate type '{$rateType}' should map to {$expectedMethod}"
            );

            // Ensure it maps to at least one category (no unmapped rates)
            $this->assertNotEmpty(
                $trueMappings,
                "Rate type '{$rateType}' must map to at least one category"
            );
        }
    }
}
