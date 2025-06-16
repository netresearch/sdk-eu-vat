<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Telemetry;

/**
 * Null Object implementation of TelemetryInterface
 *
 * This implementation provides a safe default when no telemetry is configured,
 * following the Null Object pattern to eliminate the need for null checks
 * throughout the codebase.
 *
 * All methods are no-ops, making this implementation completely silent
 * with minimal performance impact (method call overhead only).
 *
 * @example Usage as default telemetry:
 * ```php
 * class ClientConfiguration
 * {
 *     public function __construct(
 *         // ... other parameters
 *         private readonly TelemetryInterface $telemetry = new NullTelemetry()
 *     ) {}
 * }
 * ```
 *
 * @example Testing without telemetry interference:
 * ```php
 * public function testVatRetrievalWithoutTelemetry(): void
 * {
 *     $config = ClientConfiguration::production()
 *         ->withTelemetry(new NullTelemetry()); // Explicitly disable telemetry
 *
 *     $client = new SoapVatRetrievalClient($config);
 *     // Test client behavior without telemetry side effects
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\Telemetry
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class NullTelemetry implements TelemetryInterface
{
    /**
     * No-op implementation of request recording
     *
     * This method intentionally does nothing, providing a safe default
     * when telemetry is not configured or desired.
     *
     * @param string               $operation Ignored
     * @param float                $duration  Ignored
     * @param array<string, mixed> $context   Ignored
     */
    public function recordRequest(string $operation, float $duration, array $context = []): void
    {
        // Intentionally empty - no telemetry recording
    }

    /**
     * No-op implementation of error recording
     *
     * This method intentionally does nothing, providing a safe default
     * when telemetry is not configured or desired.
     *
     * @param string               $operation Ignored
     * @param string               $errorType Ignored
     * @param array<string, mixed> $context   Ignored
     */
    public function recordError(string $operation, string $errorType, array $context = []): void
    {
        // Intentionally empty - no telemetry recording
    }
}
