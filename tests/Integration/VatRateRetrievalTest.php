<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRate;

/**
 * Integration tests for successful VAT rate retrieval scenarios
 *
 * @group integration
 * @group network
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class VatRateRetrievalTest extends IntegrationTestCase
{
    /**
     * Test successful retrieval of VAT rates for a single member state
     *
     * @test
     */
    public function testRetrieveSingleCountryVatRates(): void
    {
        $this->setupVcr('vat-rates-single-country-de');

        // Request VAT rates for Germany on a specific date
        $request = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Assert response structure
        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertCount(1, $response->getResults());

        // Verify German VAT rate result
        $germanResult = $response->getResults()[0];
        $this->assertInstanceOf(VatRateResult::class, $germanResult);
        $this->assertEquals('DE', $germanResult->getMemberState());

        // Verify VAT rate details
        $vatRate = $germanResult->getVatRate();
        $this->assertInstanceOf(VatRate::class, $vatRate);
        $this->assertEquals('STANDARD', $vatRate->getType());
        $this->assertEquals('19.0', $vatRate->getValue()->__toString());

        // Verify date handling
        $this->assertValidEuDateFormat($germanResult->getSituationOn()->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $germanResult->getSituationOn()->format('Y-m-d'));
    }

    /**
     * Test retrieval of VAT rates for multiple EU member states
     *
     * @test
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

        // Assert we got results for all requested countries
        $this->assertCount(5, $response->getResults());

        // Collect results by country for easier testing
        $resultsByCountry = [];
        foreach ($response->getResults() as $result) {
            $resultsByCountry[$result->getMemberState()] = $result;
        }

        // Verify each country is present
        $this->assertArrayHasKey('DE', $resultsByCountry);
        $this->assertArrayHasKey('FR', $resultsByCountry);
        $this->assertArrayHasKey('IT', $resultsByCountry);
        $this->assertArrayHasKey('ES', $resultsByCountry);
        $this->assertArrayHasKey('NL', $resultsByCountry);

        // Verify some known VAT rates (as of 2024)
        $this->assertEquals('19.0', $resultsByCountry['DE']->getVatRate()->getValue()->__toString());
        $this->assertEquals('20.0', $resultsByCountry['FR']->getVatRate()->getValue()->__toString());
        $this->assertEquals('22.0', $resultsByCountry['IT']->getVatRate()->getValue()->__toString());
        $this->assertEquals('21.0', $resultsByCountry['ES']->getVatRate()->getValue()->__toString());
        $this->assertEquals('21.0', $resultsByCountry['NL']->getVatRate()->getValue()->__toString());

        // All should be STANDARD rates
        foreach ($resultsByCountry as $result) {
            $this->assertEquals('STANDARD', $result->getVatRate()->getType());
        }
    }

    /**
     * Test retrieval with historical date (Brexit transition)
     *
     * @test
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
        $this->assertCount(2, $response->getResults());

        $resultsByCountry = [];
        foreach ($response->getResults() as $result) {
            $resultsByCountry[$result->getMemberState()] = $result;
        }

        // Verify UK VAT rate from 2020
        $this->assertArrayHasKey('GB', $resultsByCountry);
        $this->assertEquals('20.0', $resultsByCountry['GB']->getVatRate()->getValue()->__toString());
    }

    /**
     * Test retrieval with all current EU member states
     *
     * @test
     */
    public function testRetrieveAllEuMemberStatesVatRates(): void
    {
        $cassetteName = 'vat-rates-all-eu-members';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // All EU member states as of 2024
        $euMemberStates = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        $request = new VatRatesRequest(
            memberStates: $euMemberStates,
            situationOn: new DateTime('2024-01-01')
        );

        $response = $this->client->retrieveVatRates($request);

        // Should get results for all 27 EU member states
        $this->assertCount(27, $response->getResults());

        // Verify all countries are present
        $returnedCountries = array_map(
            fn($result) => $result->getMemberState(),
            $response->getResults()
        );

        sort($euMemberStates);
        sort($returnedCountries);

        $this->assertEquals($euMemberStates, $returnedCountries);

        // All should have valid VAT rates
        foreach ($response->getResults() as $result) {
            $vatRate = $result->getVatRate();
            $this->assertNotNull($vatRate);
            $this->assertGreaterThan(0, $vatRate->getValue()->toFloat());
            $this->assertLessThanOrEqual(27, $vatRate->getValue()->toFloat()); // Hungary has 27%
        }
    }

    /**
     * Test decimal precision handling
     *
     * @test
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

        foreach ($response->getResults() as $result) {
            $vatRateString = $result->getVatRate()->getValue()->__toString();

            // VAT rates should maintain proper decimal precision
            $this->assertMatchesRegularExpression(
                '/^\d+\.\d+$/',
                $vatRateString,
                "VAT rate should be in decimal format"
            );

            // Check that trailing zeros are preserved (e.g., "17.0" not "17")
            if (strpos($vatRateString, '.0') !== false) {
                $this->assertStringEndsWith('.0', $vatRateString);
            }
        }
    }
}
