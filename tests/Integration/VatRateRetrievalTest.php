<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for successful VAT rate retrieval scenarios
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
#[Group('integration')]
#[Group('network')]
class VatRateRetrievalTest extends IntegrationTestCase
{
    /**
     * Test successful retrieval of VAT rates for a single member state
     */
    public function testRetrieveSingleCountryVatRates(): void
    {
        // Use descriptive cassette name or fall back to auto-generated name
        $this->setupVcr('vat-rates-single-country-de');

        // Request VAT rates for Germany on a specific date
        $request = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Assert response structure (the service returns one result per rate category)
        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertGreaterThan(0, count($response->getResults()));

        foreach ($response->getResults() as $result) {
            $this->assertInstanceOf(VatRateResult::class, $result);
            $this->assertEquals('DE', $result->getMemberState());
        }

        // Verify the German standard VAT rate
        $germanResult = $this->findStandardRateResult($response->getResults(), 'DE');
        $this->assertNotNull($germanResult, 'Should find standard VAT rate for Germany');

        // Verify VAT rate details
        $vatRate = $germanResult->getRate();
        $this->assertInstanceOf(VatRate::class, $vatRate);
        $this->assertEquals('DEFAULT', $vatRate->getType());
        $this->assertEquals('19.0', $vatRate->getValue());

        // Verify date handling
        $this->assertValidEuDateFormat($germanResult->getSituationOn()->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $germanResult->getSituationOn()->format('Y-m-d'));
    }

    /**
     * Test retrieval of VAT rates for multiple EU member states
     */
    public function testRetrieveMultipleCountriesVatRates(): void
    {
        $cassetteName = 'vat-rates-multiple-countries';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request VAT rates for multiple countries
        $request = new VatRatesRequest(
            memberStates: ['DE', 'FR', 'IT', 'ES', 'NL'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // The service returns one result per rate category and country
        $this->assertGreaterThan(0, count($response->getResults()));

        // Collect the standard rate per country for easier testing
        $resultsByCountry = [];
        foreach (['DE', 'FR', 'IT', 'ES', 'NL'] as $memberState) {
            $standardResult = $this->findStandardRateResult($response->getResults(), $memberState);
            $this->assertNotNull($standardResult, "Should find standard VAT rate for {$memberState}");
            $resultsByCountry[$memberState] = $standardResult;
        }

        // Verify some known VAT rates (as of 2024)
        $this->assertEquals('19.0', $resultsByCountry['DE']->getRate()->getValue());
        $this->assertEquals('20.0', $resultsByCountry['FR']->getRate()->getValue());
        $this->assertEquals('22.0', $resultsByCountry['IT']->getRate()->getValue());
        $this->assertEquals('21.0', $resultsByCountry['ES']->getRate()->getValue());
        $this->assertEquals('21.0', $resultsByCountry['NL']->getRate()->getValue());

        // All standard results carry the DEFAULT rate type
        foreach ($resultsByCountry as $result) {
            $this->assertEquals('DEFAULT', $result->getRate()->getType());
        }
    }

    /**
     * Test retrieval with historical date (Brexit transition)
     */
    public function testRetrieveHistoricalVatRates(): void
    {
        $cassetteName = 'vat-rates-historical-brexit';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request VAT rates before Brexit (UK was still in EU)
        $request = new VatRatesRequest(
            memberStates: ['GB', 'DE'],
            situationOn: new DateTime('2020-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Should get results for both countries when UK was in EU
        $this->assertGreaterThan(0, count($response->getResults()));

        // The service reports the United Kingdom as 'UK' in its responses
        $ukResult = $this->findStandardRateResult($response->getResults(), 'UK');
        $this->assertNotNull($ukResult, 'Should find standard VAT rate for the UK in 2020');

        // Verify UK VAT rate from 2020
        $this->assertEquals('20.0', $ukResult->getRate()->getValue());
    }

    /**
     * Test retrieval with all current EU member states
     */
    public function testRetrieveAllEuMemberStatesVatRates(): void
    {
        $cassetteName = 'vat-rates-all-eu-members';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // All EU member states as of 2024 (the service uses 'EL' for Greece)
        $euMemberStates = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'EL', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        $request = new VatRatesRequest(
            memberStates: $euMemberStates,
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Verify all countries are present (multiple results per country)
        $returnedCountries = array_values(array_unique(array_map(
            fn($result): string => $result->getMemberState(),
            $response->getResults()
        )));

        sort($euMemberStates);
        sort($returnedCountries);

        $this->assertEquals($euMemberStates, $returnedCountries);

        // Every member state should have a valid standard VAT rate
        foreach ($euMemberStates as $memberState) {
            $standardResult = $this->findStandardRateResult($response->getResults(), $memberState);
            $this->assertNotNull($standardResult, "Should find standard VAT rate for {$memberState}");
            $decimalValue = $standardResult->getRate()->getDecimalValue();
            $this->assertNotNull($decimalValue);
            $this->assertGreaterThan(0, $decimalValue->toFloat());
            $this->assertLessThanOrEqual(27, $decimalValue->toFloat()); // Hungary has 27%
        }
    }

    /**
     * Test decimal precision handling
     */
    public function testVatRateDecimalPrecision(): void
    {
        $cassetteName = 'vat-rates-decimal-precision';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request rates for countries with decimal VAT rates
        $request = new VatRatesRequest(
            memberStates: ['LU', 'MT'], // Luxembourg and Malta have had decimal rates
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Precision guarantee: the value accessors return exactly the literal the
        // service put on the wire. The recorded bodies carry <value>17.0</value> for
        // LU and <value>18.0</value> for MT, so the trailing zero must survive
        // decoding. BigDecimalTypeConverter registers for xs:double - the type
        // VatRetrievalServiceType.xsd actually declares for rateValueType/value - and
        // parses the element text verbatim, so no PHP float ever touches the value.
        // A regression to a float round-trip would surface here as "17"/"18".
        $rawValuesAsReceived = ['LU' => '17.0', 'MT' => '18.0'];

        foreach ($rawValuesAsReceived as $memberState => $rawValue) {
            $standardResult = $this->findStandardRateResult($response->getResults(), $memberState);
            $this->assertNotNull($standardResult, "Should find standard VAT rate for {$memberState}");

            $this->assertSame(
                $rawValue,
                $standardResult->getRate()->getRawValue(),
                sprintf(
                    'Raw value for %s must preserve the scale sent by the service; '
                    . 'a scale-less value means the xs:double typemap stopped firing.',
                    $memberState
                )
            );

            $decimalValue = $standardResult->getRate()->getValue();
            $this->assertNotNull($decimalValue);
            $this->assertSame(
                $rawValue,
                (string) $decimalValue,
                "Expected {$memberState} standard rate {$rawValue}, got {$decimalValue}"
            );
        }
    }

    /**
     * Test that the categories the service reports survive conversion
     *
     * The recorded German response for 2024-01-01 carries 36 results, 34 of which
     * name a category on the vatRateResults element; the two DEFAULT (standard)
     * results carry none. Both halves are asserted here: a real identifier and its
     * description must arrive intact, and the uncategorised standard rate must
     * report null without disturbing the rest of the response.
     */
    public function testRetrievedResultsCarryCategories(): void
    {
        $this->setupVcr('vat-rates-single-country-de');

        $request = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // NEWSPAPERS appears twice in the recording, both times as a 7.0 reduced rate
        $newspaperResults = $response->getResultsByCategory('NEWSPAPERS');
        $this->assertNotEmpty(
            $newspaperResults,
            'The recorded response reports a NEWSPAPERS category that must survive conversion'
        );

        foreach ($newspaperResults as $result) {
            $this->assertEquals('NEWSPAPERS', $result->getCategory());
            $this->assertEquals('Newspapers', $result->getCategoryDescription());
            $this->assertEquals('REDUCED_RATE', $result->getRate()->getType());
            $this->assertEquals('7.0', (string) $result->getRate()->getValue());
        }

        // An unknown identifier yields no results rather than everything
        $this->assertEquals([], $response->getResultsByCategory('NO_SUCH_CATEGORY'));

        // The standard rate is reported without a category
        $standardResult = $this->findStandardRateResult($response->getResults(), 'DE');
        $this->assertNotNull($standardResult);
        $this->assertNull($standardResult->getCategory());
        $this->assertNull($standardResult->getCategoryDescription());

        // Only a subset of results is categorised, so the filter really filters
        $categorised = array_filter(
            $response->getResults(),
            static fn(VatRateResult $result): bool => $result->getCategory() !== null
        );
        $this->assertGreaterThan(count($newspaperResults), count($categorised));
        $this->assertLessThan(count($response->getResults()), count($categorised));
    }

    /**
     * Find the standard-rate result (rate type DEFAULT) for a member state
     *
     * Regional variants (e.g. the Canary Islands for ES) also carry the
     * DEFAULT rate type but are marked with a comment, so the territory-wide
     * standard rate is the DEFAULT result without a comment.
     *
     * @param array<VatRateResult> $results     Results returned by the service
     * @param string               $memberState Two-letter member state code
     */
    private function findStandardRateResult(array $results, string $memberState): ?VatRateResult
    {
        $fallback = null;

        foreach ($results as $result) {
            if ($result->getMemberState() !== $memberState) {
                continue;
            }

            if ($result->getRate()->getType() !== 'DEFAULT') {
                continue;
            }

            if ($result->getComment() === null) {
                return $result;
            }

            $fallback ??= $result;
        }

        return $fallback;
    }
}
