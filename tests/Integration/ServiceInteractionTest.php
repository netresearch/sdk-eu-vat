<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use PHPUnit\Framework\Attributes\Group;
use Soap\ExtSoapEngine\AbusedClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use VCR\Util\SoapClient as VcrSoapClient;
use VCR\VCR;
use VCR\VCREvents;

/**
 * Guards on how the SDK talks to the service
 *
 * Two properties matter regardless of how fast the machine running them is: a
 * request for every member state must cost exactly one SOAP interaction rather
 * than one per country, and a request no cassette answers must fail locally
 * instead of travelling to the EU VAT service. Both have failed here before.
 *
 * The cassettes are owned by the functional integration tests (VatRateRetrievalTest)
 * and are only read here. This class never records: it inserts cassettes directly
 * instead of going through setupVcr(), so REFRESH_CASSETTES cannot turn a run of
 * this class into live traffic.
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
#[Group('integration')]
class ServiceInteractionTest extends IntegrationTestCase
{
    /**
     * The date recorded in every cassette this class replays
     */
    private const string SITUATION_ON = '2024-01-01';

    /**
     * Member states recorded in the vat-rates-all-eu-members cassette
     *
     * 'EL' is the code the service itself uses for Greece.
     *
     * @var array<string>
     */
    private const array ALL_MEMBER_STATES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'EL', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Rows in the vat-rates-all-eu-members cassette
     */
    private const int ALL_MEMBER_RESULT_COUNT = 1128;

    /**
     * Number of SOAP interactions VCR answered from a cassette
     */
    private static int $playbackCount = 0;

    /**
     * Number of requests VCR let out to the network
     */
    private static int $networkCount = 0;

    /**
     * Whether the counting listeners are already attached to VCR's dispatcher
     */
    private static bool $countersAttached = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSoapTransportIsIntercepted();
        $this->attachVcrCounters();
    }

    /**
     * A request for every member state must cost exactly one service call
     *
     * This is the guard against a per-item network call sneaking into the client:
     * 27 member states still have to travel in a single SOAP interaction. It also
     * pins the shape of the recorded data -- one row per rate type per member state.
     */
    public function testFullEuResponseIsRetrievedInASingleServiceCall(): void
    {
        $this->insertCassette('vat-rates-all-eu-members');

        $response = $this->replay(self::ALL_MEMBER_STATES);
        $results = $response->getResults();

        $this->assertCount(
            self::ALL_MEMBER_RESULT_COUNT,
            $results,
            'The recorded full-EU response carries one row per rate type per member state'
        );

        $returnedStates = array_unique(array_map(
            static fn(VatRateResult $result): string => $result->getMemberState(),
            $results
        ));
        sort($returnedStates);

        $expectedStates = self::ALL_MEMBER_STATES;
        sort($expectedStates);

        $this->assertSame($expectedStates, $returnedStates, 'Every requested member state must appear in the response');

        $this->assertSame(
            1,
            self::$playbackCount,
            'All 27 member states must be retrieved in one SOAP interaction, not one call per member state'
        );
        $this->assertNoNetworkTraffic();
    }

    /**
     * A request that no cassette answers must fail instead of reaching the service
     *
     * This is the proof that the suite is offline: VCR runs in 'once' mode, so any
     * request whose SOAP body differs from a recording is rejected locally rather
     * than being forwarded to the EU VAT service.
     */
    public function testUnrecordedRequestIsRejectedInsteadOfReachingTheNetwork(): void
    {
        // The cassette holds a request for DE only; asking for PT alters the SOAP body
        $this->insertCassette('vat-rates-single-country-de');

        try {
            $this->replay(['PT']);
            $this->fail('An unrecorded request must not be answered');
        } catch (VatServiceException $exception) {
            $this->assertStringContainsString(
                'does not match a previously recorded request',
                $exception->getMessage(),
                'The request must be rejected by VCR, not by the service'
            );
        }

        $this->assertNoNetworkTraffic();
    }

    /**
     * Replay one recorded call
     *
     * php-vcr indexes identical requests so a cassette can hold several recordings of
     * the same call. Resetting that index before every replay lets a single recording
     * answer an arbitrary number of identical requests, which is what a benchmark loop
     * needs.
     *
     * @param array<string> $memberStates Member states to request
     */
    private function replay(array $memberStates): VatRatesResponse
    {
        VCR::resetIndex();

        return $this->client->retrieveVatRates(new VatRatesRequest(
            memberStates: $memberStates,
            situationOn: new DateTime(self::SITUATION_ON)
        ));
    }

    /**
     * Assert that nothing left the machine during the current test
     */
    private function assertNoNetworkTraffic(): void
    {
        $this->assertSame(
            0,
            self::$networkCount,
            'Benchmarks must replay cassettes only; no request may reach the EU VAT service'
        );
    }

    /**
     * Assert that VCR really sits in front of the SOAP transport
     *
     * php-vcr rewrites the parent of the ext-soap transport while the file is being
     * included, so interception is decided once per process and cannot be repaired
     * afterwards. Checking it before the first request means a mis-bootstrapped run
     * fails here instead of quietly benchmarking the live EU VAT service.
     */
    private function assertSoapTransportIsIntercepted(): void
    {
        // Read the parent through reflection: in the source AbusedClient extends
        // \SoapClient, and only the rewritten copy VCR loads extends VcrSoapClient.
        $parent = new \ReflectionClass(AbusedClient::class)->getParentClass();

        $this->assertNotFalse($parent, 'The SOAP transport must extend a client class');
        $this->assertSame(
            VcrSoapClient::class,
            $parent->getName(),
            'VCR is not intercepting SOAP: the transport was loaded before VCR was turned on, '
            . 'so every request would reach the live EU VAT service. '
            . 'Check the bootstrap in phpunit.xml.'
        );
    }

    /**
     * Reset the interaction counters and attach the listeners that feed them
     */
    private function attachVcrCounters(): void
    {
        self::$playbackCount = 0;
        self::$networkCount = 0;

        if (self::$countersAttached) {
            return;
        }

        $dispatcher = VCR::getEventDispatcher();
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->addListener(
            VCREvents::VCR_BEFORE_PLAYBACK,
            static function (): void {
                self::$playbackCount++;
            }
        );
        $dispatcher->addListener(
            VCREvents::VCR_BEFORE_HTTP_REQUEST,
            static function (): void {
                self::$networkCount++;
            }
        );

        self::$countersAttached = true;
    }
}
