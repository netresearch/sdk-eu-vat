<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Converter;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Netresearch\EuVatSdk\Converter\VatRatesResponseConverter;
use Netresearch\EuVatSdk\Exception\ConversionException;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * VatRetrievalServiceType.xsd declares the rate "value" element with
     * minOccurs="0" inside rateValueType, which applies to every member of
     * rateValueTypeEnum. A missing value is schema-valid for any rate type,
     * so conversion must yield a VatRate with a null value instead of failing.
     */
    #[DataProvider('provideRateTypes')]
    public function testConvertsRateWithoutValueForAnyRateType(string $rateType): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData($rateType, null);

        $results = $this->converter->convert($response)->getResults();

        $this->assertCount(1, $results);
        $rate = $results[0]->getRate();
        $this->assertSame($rateType, $rate->getType());
        $this->assertNull($rate->getValue());
        $this->assertNull($rate->getRawValue());
    }

    /**
     * All rate types declared by rateValueTypeEnum in VatRetrievalServiceType.xsd.
     *
     * @return array<string, array{string}>
     */
    public static function provideRateTypes(): array
    {
        return [
            'DEFAULT' => ['DEFAULT'],
            'REDUCED_RATE' => ['REDUCED_RATE'],
            'SUPER_REDUCED_RATE' => ['SUPER_REDUCED_RATE'],
            'PARKING_RATE' => ['PARKING_RATE'],
            'NOT_APPLICABLE' => ['NOT_APPLICABLE'],
            'OUT_OF_SCOPE' => ['OUT_OF_SCOPE'],
            'EXEMPTED' => ['EXEMPTED'],
        ];
    }

    /**
     * A single member state without a parking rate must not discard the rates
     * of all other member states in the same multi-country response.
     */
    public function testValuelessRateDoesNotDiscardOtherResults(): void
    {
        $withValue = $this->createResultData('DEFAULT', BigDecimal::of('19.0'));
        $withValue->memberState = 'DE';

        $withoutValue = $this->createResultData('PARKING_RATE', null);
        $withoutValue->memberState = 'FR';

        $trailing = $this->createResultData('DEFAULT', BigDecimal::of('21.0'));
        $trailing->memberState = 'NL';

        $response = new stdClass();
        $response->vatRateResults = [$withValue, $withoutValue, $trailing];

        $results = $this->converter->convert($response)->getResults();

        $this->assertCount(3, $results);
        $this->assertSame(['DE', 'FR', 'NL'], array_map(
            static fn($result): string => $result->getMemberState(),
            $results
        ));
        $this->assertSame('19.0', $results[0]->getRate()->getRawValue());
        $this->assertNull($results[1]->getRate()->getRawValue());
        $this->assertSame('21.0', $results[2]->getRate()->getRawValue());
    }

    /**
     * Accepting an absent value must not weaken validation of the elements the
     * XSD declares as mandatory: "type" has no minOccurs="0".
     */
    public function testThrowsWhenRateTypeIsMissing(): void
    {
        $result = $this->createResultData('DEFAULT', BigDecimal::of('19.0'));
        unset($result->rate->type);

        $response = new stdClass();
        $response->vatRateResults = $result;

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Missing "type" for VAT rate.');

        $this->converter->convert($response);
    }

    /**
     * A present but non-numeric value is not schema-valid for xs:double and
     * must still be rejected.
     */
    public function testThrowsWhenValueIsNotNumeric(): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData('DEFAULT', 'not-a-number');

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Expected "value" to be a BigDecimal object or numeric');

        $this->converter->convert($response);
    }

    /**
     * Attaches a category to a raw result the way the service sends it: on the
     * vatRateResults element as a sibling of "rate", per VatRetrievalServiceType.xsd.
     */
    private function attachCategory(stdClass $result, ?string $identifier, ?string $description): stdClass
    {
        $category = new stdClass();
        if ($identifier !== null) {
            $category->identifier = $identifier;
        }
        if ($description !== null) {
            $category->description = $description;
        }
        $result->category = $category;

        return $result;
    }

    public function testConvertsResultCategory(): void
    {
        $result = $this->attachCategory(
            $this->createResultData('REDUCED_RATE', BigDecimal::of('7.0')),
            'FOODSTUFFS',
            'Foodstuffs for human and animal consumption'
        );

        $response = new stdClass();
        $response->vatRateResults = $result;

        $results = $this->converter->convert($response)->getResults();

        $this->assertSame('FOODSTUFFS', $results[0]->getCategory());
        $this->assertSame('Foodstuffs for human and animal consumption', $results[0]->getCategoryDescription());
    }

    /**
     * The XSD declares category with minOccurs="0" and the service omits it for
     * standard rates, so its absence must yield null rather than an error.
     */
    public function testResultWithoutCategoryYieldsNull(): void
    {
        $response = new stdClass();
        $response->vatRateResults = $this->createResultData('DEFAULT', BigDecimal::of('19.0'));

        $results = $this->converter->convert($response)->getResults();

        $this->assertNull($results[0]->getCategory());
        $this->assertNull($results[0]->getCategoryDescription());
    }

    /**
     * A result without a category must not discard the categories of the results
     * around it, nor abort the response.
     */
    public function testMissingCategoryDoesNotDiscardOtherResults(): void
    {
        $reduced = $this->attachCategory(
            $this->createResultData('REDUCED_RATE', BigDecimal::of('7.0')),
            'FOODSTUFFS',
            'Foodstuffs'
        );
        $reduced->memberState = 'DE';

        $standard = $this->createResultData('DEFAULT', BigDecimal::of('19.0'));
        $standard->memberState = 'DE';

        $trailing = $this->attachCategory(
            $this->createResultData('REDUCED_RATE', BigDecimal::of('10.0')),
            'ACCOMMODATION',
            'Accommodation'
        );
        $trailing->memberState = 'IT';

        $response = new stdClass();
        $response->vatRateResults = [$reduced, $standard, $trailing];

        $converted = $this->converter->convert($response);

        $this->assertCount(3, $converted->getResults());
        $this->assertSame(['FOODSTUFFS', null, 'ACCOMMODATION'], array_map(
            static fn($result): ?string => $result->getCategory(),
            $converted->getResults()
        ));

        $foodstuffs = $converted->getResultsByCategory('FOODSTUFFS');
        $this->assertCount(1, $foodstuffs);
        $this->assertSame('DE', $foodstuffs[0]->getMemberState());
        $this->assertSame([], $converted->getResultsByCategory('PHARMACEUTICAL_PRODUCTS'));
    }

    /**
     * A malformed category (element present but without a usable identifier) must
     * degrade to null instead of aborting the whole response.
     */
    #[DataProvider('malformedCategoryProvider')]
    public function testMalformedCategoryDegradesToNull(?string $identifier, ?string $description): void
    {
        $result = $this->attachCategory(
            $this->createResultData('REDUCED_RATE', BigDecimal::of('7.0')),
            $identifier,
            $description
        );

        $response = new stdClass();
        $response->vatRateResults = $result;

        $results = $this->converter->convert($response)->getResults();

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->getCategory());
        $this->assertNull($results[0]->getCategoryDescription());
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function malformedCategoryProvider(): array
    {
        return [
            'identifier element absent' => [null, 'Foodstuffs'],
            'identifier empty' => ['', 'Foodstuffs'],
            'identifier blank' => ['   ', 'Foodstuffs'],
        ];
    }

    /**
     * The XSD requires a description inside a category element, but a response that
     * omits it must still surrender the identifier the filter API depends on.
     */
    public function testCategoryWithoutDescriptionKeepsIdentifier(): void
    {
        $result = $this->attachCategory(
            $this->createResultData('REDUCED_RATE', BigDecimal::of('7.0')),
            'SUPPLY_WATER',
            null
        );

        $response = new stdClass();
        $response->vatRateResults = $result;

        $results = $this->converter->convert($response)->getResults();

        $this->assertSame('SUPPLY_WATER', $results[0]->getCategory());
        $this->assertNull($results[0]->getCategoryDescription());
    }
}
