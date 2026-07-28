<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Converter;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Netresearch\EuVatSdk\Converter\VatRatesResponseConverter;
use Netresearch\EuVatSdk\Exception\ConversionException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Test VatRatesResponseConverter
 */
class VatRatesResponseConverterTest extends TestCase
{
    private VatRatesResponseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new VatRatesResponseConverter();
    }

    /**
     * Builds a raw SOAP-style result object as produced by ext-soap-engine.
     *
     * @param string $rateType Rate type enum value (e.g. 'DEFAULT', 'EXEMPTED').
     * @param BigDecimal|string|null $rateValue Rate value, or null to omit the element entirely.
     */
    private function createResultData(string $rateType, BigDecimal|string|null $rateValue): stdClass
    {
        $rate = new stdClass();
        $rate->type = $rateType;
        if ($rateValue !== null) {
            $rate->value = $rateValue;
        }

        $result = new stdClass();
        $result->memberState = 'DE';
        $result->type = 'STANDARD';
        $result->rate = $rate;
        $result->situationOn = new DateTimeImmutable('2024-01-01');

        return $result;
    }

    public function testConvertsRateWithValue(): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData('DEFAULT', BigDecimal::of('19.0'));

        $results = $this->converter->convert($response)->getResults();

        $this->assertCount(1, $results);
        $rate = $results[0]->getRate();
        $this->assertEquals('DEFAULT', $rate->getType());
        $this->assertEquals('19.0', (string) $rate->getValue());
    }

    /**
     * The XSD declares the rate "value" element with minOccurs="0": exempt and
     * out-of-scope rate types legitimately arrive without a percentage value.
     *
     * @dataProvider provideValuelessRateTypes
     */
    public function testConvertsExemptRateWithoutValue(string $rateType): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData($rateType, null);

        $results = $this->converter->convert($response)->getResults();

        $this->assertCount(1, $results);
        $rate = $results[0]->getRate();
        $this->assertTrue($rate->isExempt());
        $this->assertNull($rate->getValue());
        $this->assertNull($rate->getRawValue());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValuelessRateTypes(): array
    {
        return [
            'EXEMPTED' => ['EXEMPTED'],
            'NOT_APPLICABLE' => ['NOT_APPLICABLE'],
            'OUT_OF_SCOPE' => ['OUT_OF_SCOPE'],
        ];
    }

    /**
     * @dataProvider provideValueRequiringRateTypes
     */
    public function testThrowsWhenValueMissingForRateTypeRequiringValue(string $rateType): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData($rateType, null);

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage(sprintf('Missing "value" for VAT rate of type "%s"', $rateType));

        $this->converter->convert($response);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValueRequiringRateTypes(): array
    {
        return [
            'DEFAULT' => ['DEFAULT'],
            'STANDARD' => ['STANDARD'],
            'REDUCED_RATE' => ['REDUCED_RATE'],
            'SUPER_REDUCED_RATE' => ['SUPER_REDUCED_RATE'],
            'PARKING_RATE' => ['PARKING_RATE'],
        ];
    }
}
