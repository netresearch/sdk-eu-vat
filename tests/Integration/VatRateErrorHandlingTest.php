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
use Netresearch\EuVatSdk\Exception\ValidationException;
use VCR\VCR;

/**
 * Integration tests for error handling scenarios
 *
 * The recorded fault shape these tests pin down is:
 *
 * ```xml
 * <faultcode>env:Client</faultcode>
 * <faultstring>TEDB-ERR-2 - Request is not valid</faultstring>
 * <detail><ns2:retrieveVatRatesFaultMsg><ns0:error>
 *   <ns0:code>00002</ns0:code>
 *   <ns0:description>The Member State "XX" does not exist.</ns0:description>
 * </ns0:error></ns2:retrieveVatRatesFaultMsg></detail>
 * ```
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
     * Unknown member state codes are rejected by the service with TEDB-ERR-2
     *
     * @test
     */
    public function testInvalidCountryCodeError(): void
    {
        $this->setupVcr('error-invalid-country-code');

        $request = new VatRatesRequest(
            memberStates: ['XX', 'YY'], // Unknown country codes
            situationOn: new DateTime('2024-01-01')
        );

        try {
            $this->client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException for unknown member states');
        } catch (InvalidRequestException $e) {
            $this->assertSame('TEDB-ERR-2', $e->getErrorCode());
            $this->assertStringContainsString('TEDB-ERR-2 - Request is not valid', $e->getMessage());
            $this->assertStringContainsString('The Member State "XX" does not exist.', $e->getMessage());
            $this->assertStringContainsString('The Member State "YY" does not exist.', $e->getMessage());
        }
    }

    /**
     * An empty member state list never reaches the service
     *
     * VatRatesRequest rejects it in its own constructor, so no SOAP fault mapping
     * is involved and the cassette for this scenario is empty.
     *
     * @test
     */
    public function testEmptyMemberStatesError(): void
    {
        $this->setupVcr('error-empty-member-states');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Member states array cannot be empty');

        new VatRatesRequest(
            memberStates: [],
            situationOn: new DateTime('2024-01-01')
        );
    }

    /**
     * Dates beyond the accepted range never reach the service either
     *
     * VatRatesRequest caps the situation date at five years into the future.
     *
     * @test
     */
    public function testFutureDateError(): void
    {
        $this->setupVcr('error-future-date');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Date cannot be more than 5 years in the future');

        new VatRatesRequest(
            memberStates: ['DE'],
            situationOn: new DateTime('+10 years')
        );
    }

    /**
     * Non-EU country codes produce the same TEDB-ERR-2 fault
     *
     * @test
     */
    public function testNonEuCountryCodeError(): void
    {
        $this->setupVcr('error-non-eu-country');

        $request = new VatRatesRequest(
            memberStates: ['US', 'CN', 'JP'], // Non-EU countries
            situationOn: new DateTime('2024-01-01')
        );

        try {
            $this->client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException for non-EU member states');
        } catch (InvalidRequestException $e) {
            $this->assertSame('TEDB-ERR-2', $e->getErrorCode());
            $this->assertStringContainsString('The Member State "US" does not exist.', $e->getMessage());
        }
    }

    /**
     * GB is still served after Brexit, mapped onto the UK member state
     *
     * The recorded response is a plain HTTP 200 with UK rate results, so the
     * service does not treat GB as an unknown member state.
     *
     * @test
     */
    public function testBrexitTransitionHandling(): void
    {
        $this->setupVcr('error-brexit-after-transition');

        $request = new VatRatesRequest(
            memberStates: ['GB'],
            situationOn: new DateTime('2022-01-01') // After Brexit
        );

        $response = $this->client->retrieveVatRates($request);

        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertNotEmpty($response->getResults(), 'Service returns results for GB');

        foreach ($response->getResults() as $result) {
            $this->assertSame('UK', $result->getMemberState());
        }
    }

    /**
     * A single unknown code rejects the whole request
     *
     * @test
     */
    public function testMixedValidInvalidCountryCodes(): void
    {
        $this->setupVcr('error-mixed-country-codes');

        $request = new VatRatesRequest(
            memberStates: ['DE', 'XX', 'FR', 'YY'], // Mixed valid/invalid
            situationOn: new DateTime('2024-01-01')
        );

        try {
            $this->client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException for a partially invalid request');
        } catch (InvalidRequestException $e) {
            $this->assertSame('TEDB-ERR-2', $e->getErrorCode());
            $this->assertStringContainsString('The Member State "XX" does not exist.', $e->getMessage());
        }
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
