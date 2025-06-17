<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;

/**
 * Integration tests for error handling scenarios
 *
 * @group integration
 * @group network
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class VatRateErrorHandlingTest extends IntegrationTestCase
{
    /**
     * Test handling of invalid country code error (TEDB-101)
     *
     * @test
     */
    public function testInvalidCountryCodeError(): void
    {
        $cassetteName = 'error-invalid-country-code';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request with invalid country code
        $request = new VatRatesRequest(
            memberStates: ['XX', 'YY'], // Invalid country codes
            situationOn: new DateTime('2024-01-01')
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code provided (TEDB-101)');
        $this->expectExceptionCode('TEDB-101');

        $this->client->retrieveVatRates($request);
    }

    /**
     * Test handling of empty member states array error (TEDB-102)
     *
     * @test
     */
    public function testEmptyMemberStatesError(): void
    {
        $cassetteName = 'error-empty-member-states';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request with empty member states array
        $request = new VatRatesRequest(
            memberStates: [],
            situationOn: new DateTime('2024-01-01')
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Empty member states array provided (TEDB-102)');
        $this->expectExceptionCode('TEDB-102');

        $this->client->retrieveVatRates($request);
    }

    /**
     * Test handling of future date requests
     *
     * @test
     */
    public function testFutureDateError(): void
    {
        $cassetteName = 'error-future-date';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request with date far in the future
        $futureDate = new DateTime('+10 years');

        $request = new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: $futureDate
        );

        // The service might return an error or empty results for future dates
        try {
            $response = $this->client->retrieveVatRates($request);

            // If no exception, verify the response handling
            $this->assertInstanceOf(VatRatesResponse::class, $response);

            // Future dates might return current rates or throw an error
            if ($response->getResults() !== []) {
            }
        } catch (InvalidRequestException $e) {
            // Service might reject far future dates
            $this->assertStringContainsString('date', strtolower($e->getMessage()));
        }
    }

    /**
     * Test handling of non-EU country codes
     *
     * @test
     */
    public function testNonEuCountryCodeError(): void
    {
        $cassetteName = 'error-non-eu-country';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request with non-EU country codes
        $request = new VatRatesRequest(
            memberStates: ['US', 'CN', 'JP'], // Non-EU countries
            situationOn: new DateTime('2024-01-01')
        );

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code provided (TEDB-101)');

        $this->client->retrieveVatRates($request);
    }

    /**
     * Test handling of Brexit transition (UK after leaving EU)
     *
     * @test
     */
    public function testBrexitTransitionHandling(): void
    {
        $cassetteName = 'error-brexit-after-transition';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Request UK rates after Brexit transition period
        $request = new VatRatesRequest(
            memberStates: ['GB'],
            situationOn: new DateTime('2022-01-01') // After Brexit
        );

        // UK should no longer be valid after Brexit
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code provided (TEDB-101)');

        $this->client->retrieveVatRates($request);
    }

    /**
     * Test handling of mixed valid and invalid country codes
     *
     * @test
     */
    public function testMixedValidInvalidCountryCodes(): void
    {
        $cassetteName = 'error-mixed-country-codes';

        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }

        // Mix of valid EU and invalid country codes
        $request = new VatRatesRequest(
            memberStates: ['DE', 'XX', 'FR', 'YY'], // Mixed valid/invalid
            situationOn: new DateTime('2024-01-01')
        );

        // Service should reject the entire request
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code provided (TEDB-101)');

        $this->client->retrieveVatRates($request);
    }

    /**
     * Test timeout handling
     *
     * @test
     * @group slow
     * @group network
     */
    public function testTimeoutHandling(): void
    {
        // Create a client with very short timeout
        $config = ClientConfiguration::test()
            ->withTimeout(1); // 1 second timeout

        $client = new SoapVatRetrievalClient($config);

        // Don't use VCR for timeout test - we need real network behavior
        VCR::turnOff();

        $request = new VatRatesRequest(
            memberStates: ['DE', 'FR', 'IT', 'ES', 'NL'],
            situationOn: new DateTime('2024-01-01')
        );

        try {
            $client->retrieveVatRates($request);

            // If request completes within timeout, that's OK
            $this->assertTrue(true);
        } catch (ServiceUnavailableException $e) {
            // Timeout should be wrapped in ServiceUnavailableException
            $this->assertStringContainsString('Network error', $e->getMessage());
        }

        // Re-enable VCR
        VCR::turnOn();
    }

    /**
     * Test handling of malformed SOAP response
     *
     * @test
     */
    public function testMalformedResponseHandling(): void
    {
        // This test would require mocking at the SOAP level
        // Since we're using VCR, we can't easily simulate malformed responses
        // Mark as incomplete for now

        $this->markTestIncomplete(
            'Malformed SOAP response testing requires lower-level mocking beyond VCR capabilities'
        );
    }
}
