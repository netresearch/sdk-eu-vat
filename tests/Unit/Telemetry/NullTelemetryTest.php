<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Telemetry;

use Netresearch\EuVatSdk\Telemetry\NullTelemetry;
use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test NullTelemetry implementation
 */
class NullTelemetryTest extends TestCase
{
    private NullTelemetry $telemetry;

    protected function setUp(): void
    {
        $this->telemetry = new NullTelemetry();
    }

    public function testImplementsTelemetryInterface(): void
    {
        $this->assertInstanceOf(TelemetryInterface::class, $this->telemetry);
    }

    public function testRecordRequestDoesNothing(): void
    {
        // Should not throw any exceptions and return void
        $this->expectNotToPerformAssertions();

        $this->telemetry->recordRequest('retrieveVatRates', 0.5, [
            'member_states' => ['DE', 'FR'],
            'situation_on' => new \DateTimeImmutable(),
            'result_count' => 2,
        ]);
    }

    public function testRecordErrorDoesNothing(): void
    {
        // Should not throw any exceptions and return void
        $this->expectNotToPerformAssertions();

        $this->telemetry->recordError('retrieveVatRates', 'InvalidRequestException', [
            'member_states' => ['XX'],
            'error_message' => 'Invalid country code',
            'error_code' => 'TEDB-101',
        ]);
    }

    public function testRecordRequestWithEmptyContext(): void
    {
        // Should handle empty context without issues
        $this->expectNotToPerformAssertions();

        $this->telemetry->recordRequest('retrieveVatRates', 1.2);
    }

    public function testRecordErrorWithEmptyContext(): void
    {
        // Should handle empty context without issues
        $this->expectNotToPerformAssertions();

        $this->telemetry->recordError('retrieveVatRates', 'ServiceUnavailableException');
    }

    public function testCanBeUsedAsDefaultTelemetry(): void
    {
        // Test that it can be used as a default without issues
        $telemetry = new NullTelemetry();

        // Simulate typical usage pattern
        $startTime = microtime(true);

        try {
            // Simulate successful operation
            $duration = microtime(true) - $startTime;
            $telemetry->recordRequest('retrieveVatRates', $duration, [
                'member_states' => ['AT', 'BE'],
                'result_count' => 2,
            ]);

            $this->assertTrue(true); // Test passed if no exceptions
        } catch (\Exception $e) {
            $telemetry->recordError('retrieveVatRates', get_class($e), [
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for proper test failure
        }
    }
}
