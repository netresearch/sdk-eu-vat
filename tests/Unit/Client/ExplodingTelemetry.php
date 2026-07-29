<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Client;

use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;

/**
 * Telemetry implementation that fails on every call
 *
 * Stands in for a broken metrics backend: the SDK must absorb its failures.
 */
final class ExplodingTelemetry implements TelemetryInterface
{
    public const MESSAGE = 'metrics backend is down';

    /**
     * @param string               $operation Ignored
     * @param float                $duration  Ignored
     * @param array<string, mixed> $context   Ignored
     */
    public function recordRequest(string $operation, float $duration, array $context = []): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    /**
     * @param string               $operation Ignored
     * @param string               $errorType Ignored
     * @param array<string, mixed> $context   Ignored
     */
    public function recordError(string $operation, string $errorType, array $context = []): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
