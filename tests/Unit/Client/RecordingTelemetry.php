<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;

/**
 * Telemetry implementation that records every call for later assertion
 */
final class RecordingTelemetry implements TelemetryInterface
{
    /**
     * @var list<array{operation: string, duration: float, context: array<string, mixed>}>
     */
    public array $requests = [];

    /**
     * @var list<array{operation: string, errorType: string, context: array<string, mixed>}>
     */
    public array $errors = [];

    /**
     * @param string               $operation Operation name reported by the SDK
     * @param float                $duration  Duration reported by the SDK
     * @param array<string, mixed> $context   Context reported by the SDK
     */
    public function recordRequest(string $operation, float $duration, array $context = []): void
    {
        $this->requests[] = [
            'operation' => $operation,
            'duration' => $duration,
            'context' => $context,
        ];
    }

    /**
     * @param string               $operation Operation name reported by the SDK
     * @param string               $errorType Error type reported by the SDK
     * @param array<string, mixed> $context   Context reported by the SDK
     */
    public function recordError(string $operation, string $errorType, array $context = []): void
    {
        $this->errors[] = [
            'operation' => $operation,
            'errorType' => $errorType,
            'context' => $context,
        ];
    }
}
