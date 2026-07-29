<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use Brick\Math\BigDecimal;
use DateTime;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Soap\Engine\Engine;
use Soap\ExtSoapEngine\Exception\RequestException;

/**
 * Verify that the client actually drives the telemetry contract it advertises
 *
 * These tests assert on what the SDK passes to a real TelemetryInterface
 * implementation, not on mock expectations, so they fail if the client stops
 * recording or records the wrong shape.
 */
class TelemetryIntegrationTest extends TestCase
{
    /**
     * Wall-clock time the faked SOAP call is made to consume, in microseconds
     */
    private const SIMULATED_CALL_MICROSECONDS = 50000;

    private RecordingTelemetry $telemetry;

    protected function setUp(): void
    {
        $this->telemetry = new RecordingTelemetry();
    }

    public function testSuccessfulCallRecordsExactlyOneRequestAndNoError(): void
    {
        $request = new VatRatesRequest(['DE', 'FR'], new DateTime('2024-01-01'));

        $response = $this->clientReturning($this->soapResponseWithTwoResults())
            ->retrieveVatRates($request);

        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertCount(1, $this->telemetry->requests, 'Exactly one request must be recorded');
        $this->assertSame([], $this->telemetry->errors, 'A successful call must record no error');

        $recorded = $this->telemetry->requests[0];
        $this->assertSame('retrieveVatRates', $recorded['operation']);
    }

    public function testSuccessfulCallRecordsTheDocumentedContext(): void
    {
        $situationOn = new DateTime('2024-01-01');
        $request = new VatRatesRequest(['DE', 'FR'], $situationOn);

        $this->clientReturning($this->soapResponseWithTwoResults())->retrieveVatRates($request);

        $context = $this->telemetry->requests[0]['context'];

        $this->assertSame(['DE', 'FR'], $context['member_states']);
        $this->assertSame($situationOn, $context['situation_on']);
        $this->assertSame(2, $context['result_count'], 'result_count must reflect the converted results');
        $this->assertSame(ClientConfiguration::ENDPOINT_TEST, $context['endpoint']);
    }

    /**
     * TelemetryInterface documents the duration as "in seconds (with microsecond precision)".
     *
     * The engine is made to consume a known amount of wall-clock time, so a duration
     * reported in milliseconds would land far outside the asserted range.
     */
    public function testRecordedDurationIsMeasuredInSeconds(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        $this->clientReturning($this->soapResponseWithTwoResults(), self::SIMULATED_CALL_MICROSECONDS)
            ->retrieveVatRates($request);

        $duration = $this->telemetry->requests[0]['duration'];
        $simulatedSeconds = self::SIMULATED_CALL_MICROSECONDS / 1000000;

        $this->assertGreaterThanOrEqual(
            $simulatedSeconds,
            $duration,
            'Duration must cover at least the time the call actually took'
        );
        $this->assertLessThan(
            $simulatedSeconds * 20,
            $duration,
            'Duration is documented in seconds; a millisecond value would exceed this bound'
        );
    }

    public function testServiceRejectionRecordsAnErrorAndNoRequest(): void
    {
        $situationOn = new DateTime('2024-01-01');
        $request = new VatRatesRequest(['XX'], $situationOn);
        $client = $this->clientThrowing(
            new \SoapFault('env:Client', 'TEDB-ERR-2 - Request is not valid')
        );

        try {
            $client->retrieveVatRates($request);
            $this->fail('Expected InvalidRequestException was not thrown');
        } catch (InvalidRequestException) {
            // Expected - the call must still fail the documented way.
        }

        $this->assertSame([], $this->telemetry->requests, 'A failed call must record no success');
        $this->assertCount(1, $this->telemetry->errors);

        $recorded = $this->telemetry->errors[0];
        $this->assertSame('retrieveVatRates', $recorded['operation']);
        $this->assertSame('InvalidRequestException', $recorded['errorType']);

        $context = $recorded['context'];
        $this->assertSame(['XX'], $context['member_states']);
        $this->assertSame($situationOn, $context['situation_on']);
        $this->assertSame('TEDB-ERR-2', $context['error_code']);
        $this->assertStringContainsString('TEDB-ERR-2', $context['error_message']);
        $this->assertSame(ClientConfiguration::ENDPOINT_TEST, $context['endpoint']);
        $this->assertGreaterThan(0.0, $context['duration']);
    }

    public function testTransportFailureRecordsErrorUnderItsOwnType(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $client = $this->clientThrowing(RequestException::internalSoapError('Network timeout'));

        try {
            $client->retrieveVatRates($request);
            $this->fail('Expected ServiceUnavailableException was not thrown');
        } catch (ServiceUnavailableException) {
            // Expected.
        }

        $this->assertCount(1, $this->telemetry->errors);
        $this->assertSame('ServiceUnavailableException', $this->telemetry->errors[0]['errorType']);
    }

    public function testThrowingTelemetryDoesNotBreakASuccessfulCall(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $client = $this->clientReturning(
            $this->soapResponseWithTwoResults(),
            0,
            new ExplodingTelemetry()
        );

        $response = $client->retrieveVatRates($request);

        $this->assertInstanceOf(VatRatesResponse::class, $response);
        $this->assertCount(2, $response->getResults(), 'The response must survive a broken telemetry sink');
    }

    public function testThrowingTelemetryDoesNotMaskTheDomainException(): void
    {
        $request = new VatRatesRequest(['XX'], new DateTime('2024-01-01'));
        $client = $this->clientThrowing(
            new \SoapFault('env:Client', 'TEDB-ERR-2 - Request is not valid'),
            new ExplodingTelemetry()
        );

        // The caller must still see the documented domain exception, not the telemetry failure.
        $this->expectException(InvalidRequestException::class);

        $client->retrieveVatRates($request);
    }

    public function testTelemetryFailureIsLoggedRatherThanSilentlyDropped(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Telemetry recording failed'),
                $this->callback(
                    static fn(array $context): bool => $context['error'] === ExplodingTelemetry::MESSAGE
                )
            );

        $config = ClientConfiguration::test($logger)->withTelemetry(new ExplodingTelemetry());

        $client = new SoapVatRetrievalClient($config, $this->engineReturning($this->soapResponseWithTwoResults(), 0));

        $client->retrieveVatRates($request);
    }

    public function testDefaultConfigurationRecordsNothingAndStillSucceeds(): void
    {
        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));

        // No telemetry configured at all - NullTelemetry must keep behaviour unchanged.
        $config = ClientConfiguration::test(new NullLogger());
        $client = new SoapVatRetrievalClient($config, $this->engineReturning($this->soapResponseWithTwoResults(), 0));

        $this->assertCount(2, $client->retrieveVatRates($request)->getResults());
    }

    /**
     * Build a client whose engine returns the given SOAP response
     *
     * @param \stdClass                $soapResponse Raw response the engine hands back
     * @param integer                  $delay        Microseconds the call should consume
     * @param TelemetryInterface|null  $telemetry    Telemetry sink, defaults to the recording one
     */
    private function clientReturning(
        \stdClass $soapResponse,
        int $delay = 0,
        ?TelemetryInterface $telemetry = null
    ): SoapVatRetrievalClient {
        return new SoapVatRetrievalClient(
            $this->configurationWith($telemetry ?? $this->telemetry),
            $this->engineReturning($soapResponse, $delay)
        );
    }

    /**
     * Build a client whose engine fails with the given throwable
     *
     * @param \Throwable              $failure   Failure raised by the engine
     * @param TelemetryInterface|null $telemetry Telemetry sink, defaults to the recording one
     */
    private function clientThrowing(
        \Throwable $failure,
        ?TelemetryInterface $telemetry = null
    ): SoapVatRetrievalClient {
        $engine = $this->createMock(Engine::class);
        $engine->expects($this->once())
            ->method('request')
            ->willThrowException($failure);

        return new SoapVatRetrievalClient(
            $this->configurationWith($telemetry ?? $this->telemetry),
            $engine
        );
    }

    private function configurationWith(TelemetryInterface $telemetry): ClientConfiguration
    {
        return ClientConfiguration::test(new NullLogger())->withTelemetry($telemetry);
    }

    /**
     * @param \stdClass $soapResponse Raw response the engine hands back
     * @param integer   $delay        Microseconds the call should consume
     */
    private function engineReturning(\stdClass $soapResponse, int $delay): Engine
    {
        $engine = $this->createMock(Engine::class);
        $engine->expects($this->once())
            ->method('request')
            ->willReturnCallback(static function () use ($soapResponse, $delay): \stdClass {
                if ($delay > 0) {
                    usleep($delay);
                }

                return $soapResponse;
            });

        return $engine;
    }

    /**
     * Raw SOAP response carrying two VAT rate results
     */
    private function soapResponseWithTwoResults(): \stdClass
    {
        $response = new \stdClass();
        $response->vatRateResults = [
            $this->soapResult('DE', '19.0'),
            $this->soapResult('FR', '20.0'),
        ];

        return $response;
    }

    /**
     * @param string $memberState Country code of the result
     * @param string $value       Rate value as the service reports it
     */
    private function soapResult(string $memberState, string $value): \stdClass
    {
        // Shaped as the TypeConverters leave it: DateTimeImmutable and BigDecimal, not strings.
        $rate = new \stdClass();
        $rate->type = 'STANDARD';
        $rate->value = BigDecimal::of($value);

        $result = new \stdClass();
        $result->memberState = $memberState;
        $result->situationOn = new \DateTimeImmutable('2024-01-01');
        $result->rate = $rate;

        return $result;
    }
}
