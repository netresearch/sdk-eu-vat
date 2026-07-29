<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use DateTime;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Soap\ExtSoapEngine\AbusedClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use VCR\Util\SoapClient as VcrSoapClient;
use VCR\VCR;
use VCR\VCREvents;

/**
 * Benchmarks for the SDK's own request and response handling overhead
 *
 * Every test in this class replays a committed cassette, so nothing here measures
 * service latency -- network timing would be pure noise on a shared CI runner and
 * could never fail for a reason the SDK controls. What is measured instead is the
 * work the SDK does on its own: XML parsing, type conversion, DTO construction and
 * the memory those DTOs occupy.
 *
 * The cassettes are owned by the functional integration tests (VatRateRetrievalTest)
 * and are only read here. This class never records: it inserts cassettes directly
 * instead of going through setupVcr(), so REFRESH_CASSETTES cannot turn a benchmark
 * run into live traffic.
 *
 * @group integration
 * @group performance
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class PerformanceBenchmarkTest extends IntegrationTestCase
{
    /**
     * The date recorded in every cassette this class replays
     */
    private const SITUATION_ON = '2024-01-01';

    /**
     * Member states recorded in the vat-rates-all-eu-members cassette
     *
     * 'EL' is the code the service itself uses for Greece.
     *
     * @var array<string>
     */
    private const ALL_MEMBER_STATES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'EL', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * Member states recorded in the vat-rates-multiple-countries cassette
     *
     * @var array<string>
     */
    private const MULTI_COUNTRY_STATES = ['DE', 'FR', 'IT', 'ES', 'NL'];

    /**
     * Rows in the vat-rates-single-country-de cassette
     *
     * The service answers with one row per rate type per member state, so a
     * single-country query yields dozens of results rather than one.
     */
    private const SINGLE_COUNTRY_RESULT_COUNT = 36;

    /**
     * Rows in the vat-rates-multiple-countries cassette
     */
    private const MULTI_COUNTRY_RESULT_COUNT = 295;

    /**
     * Rows in the vat-rates-all-eu-members cassette
     */
    private const ALL_MEMBER_RESULT_COUNT = 1128;

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
     *
     * @test
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
     * Response handling cost must stay proportional to the number of results
     *
     * The absolute cost of parsing a response depends entirely on how fast the
     * runner is, so the stable signal is the cost *per result*: it barely changes
     * between a 36-row and a 1128-row response as long as the handling is linear,
     * and it grows with the response size as soon as something is accidentally
     * quadratic (a nested loop over the results, a repeated in_array() lookup).
     *
     * Both sizes are measured in the same interleaved rounds and the cheapest round
     * wins, because scheduling noise can only ever make a measurement slower.
     *
     * @test
     */
    public function testResponseHandlingCostStaysLinearInResultCount(): void
    {
        $smallCosts = [];
        $largeCosts = [];

        for ($round = 0; $round < 3; $round++) {
            $this->insertCassette('vat-rates-single-country-de');
            $smallCosts[] = $this->measureCostPerResult(['DE'], 40, self::SINGLE_COUNTRY_RESULT_COUNT);

            $this->insertCassette('vat-rates-all-eu-members');
            $largeCosts[] = $this->measureCostPerResult(self::ALL_MEMBER_STATES, 10, self::ALL_MEMBER_RESULT_COUNT);
        }

        $smallCost = min($smallCosts);
        $largeCost = min($largeCosts);

        // A quadratic regression would make the 1128-row response roughly 31 times
        // more expensive per result than the 36-row one. Linear handling lands near
        // 1.4 (the small response still carries the fixed per-call overhead), so a
        // factor of 10 sits far above the noise floor and far below the failure mode.
        $this->assertLessThan(
            10.0,
            $largeCost / $smallCost,
            'Cost per result must not grow with the response size; a large factor means quadratic handling'
        );

        // A per-item network call would cost milliseconds per result even on a fast
        // link, so this ceiling (~65x the observed cost) catches an accidental round
        // trip or a comparably catastrophic slowdown without reacting to jitter.
        $this->assertLessThan(
            2.0,
            $largeCost,
            'Handling a single result must stay far below a millisecond'
        );
    }

    /**
     * The memory a response occupies must stay proportional to its result count
     *
     * Guards against the DTO layer retaining something per result that it should not,
     * such as the raw XML fragment or a copy of the whole response.
     *
     * @test
     */
    public function testLargeResponseMemoryFootprintStaysProportional(): void
    {
        $this->insertCassette('vat-rates-all-eu-members');

        // Warm up so that lazily built SOAP structures are not charged to the measurement
        $this->replay(self::ALL_MEMBER_STATES);
        gc_collect_cycles();

        $memoryBefore = memory_get_usage();
        $response = $this->replay(self::ALL_MEMBER_STATES);
        $memoryAfter = memory_get_usage();

        $this->assertCount(self::ALL_MEMBER_RESULT_COUNT, $response->getResults());

        // A result is a small DTO holding a member state, a date and a rate; it needs
        // well under a kilobyte. The ceiling is deliberately several times that so it
        // only fires on a structural change, not on allocator granularity.
        $bytesPerResult = ($memoryAfter - $memoryBefore) / self::ALL_MEMBER_RESULT_COUNT;
        $this->assertLessThan(
            4096,
            $bytesPerResult,
            'Each result DTO must stay small; a jump here means the response retains more than it needs'
        );

        $this->assertNoNetworkTraffic();
    }

    /**
     * Repeated calls must not accumulate memory
     *
     * Guards against the client caching every request or response in a static or
     * instance-level structure that is never released.
     *
     * @test
     */
    public function testRepeatedCallsDoNotAccumulateMemory(): void
    {
        $this->insertCassette('vat-rates-multiple-countries');

        // Warm up: the first calls populate caches that are expected to be bounded
        for ($i = 0; $i < 5; $i++) {
            $this->replay(self::MULTI_COUNTRY_STATES);
        }
        gc_collect_cycles();

        $memoryBefore = memory_get_usage();

        $iterations = 30;
        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->replay(self::MULTI_COUNTRY_STATES);
            $this->assertCount(self::MULTI_COUNTRY_RESULT_COUNT, $response->getResults());
            unset($response);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $memoryBefore;

        // Retaining even one of the 30 responses would cost roughly 260 KB, so a 1 MB
        // ceiling flags real accumulation while tolerating bookkeeping noise.
        $this->assertLessThan(
            1024 * 1024,
            $growth,
            "Memory must not grow across {$iterations} identical calls"
        );

        $this->assertNoNetworkTraffic();
    }

    /**
     * A request that no cassette answers must fail instead of reaching the service
     *
     * This is the proof that the suite is offline: VCR runs in 'once' mode, so any
     * request whose SOAP body differs from a recording is rejected locally rather
     * than being forwarded to the EU VAT service.
     *
     * @test
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
     * Measure the average cost of handling one result, in milliseconds
     *
     * @param array<string> $memberStates Member states to request
     * @param integer       $iterations   Number of replays to average over
     * @param integer       $resultCount  Number of results the cassette returns
     */
    private function measureCostPerResult(array $memberStates, int $iterations, int $resultCount): float
    {
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->replay($memberStates);
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        return $elapsedMs / $iterations / $resultCount;
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
        $parent = (new \ReflectionClass(AbusedClient::class))->getParentClass();

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
