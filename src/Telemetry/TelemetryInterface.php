<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Telemetry;

/**
 * Interface for recording telemetry data from SDK operations
 *
 * This interface provides hooks for recording metrics and observability data
 * from EU VAT SDK operations. It is designed to be lightweight and focused
 * on the most valuable metrics for production monitoring.
 *
 * Unlike PSR-3 logging which records discrete events, telemetry focuses on
 * measurable metrics like counters, gauges, and timing data that can be
 * aggregated and visualized for operational insights.
 *
 * @example Integration with monitoring systems:
 * ```php
 * class PrometheusVatTelemetry implements TelemetryInterface
 * {
 *     public function recordRequest(string $operation, float $duration, array $context = []): void
 *     {
 *         $this->histogram->observe($duration, ['operation' => $operation]);
 *         $this->counter->inc(['operation' => $operation, 'status' => 'success']);
 *     }
 *
 *     public function recordError(string $operation, string $errorType, array $context = []): void
 *     {
 *         $this->counter->inc(['operation' => $operation, 'status' => 'error', 'type' => $errorType]);
 *     }
 * }
 * ```
 *
 * @example Custom analytics integration:
 * ```php
 * class AnalyticsVatTelemetry implements TelemetryInterface
 * {
 *     public function recordRequest(string $operation, float $duration, array $context = []): void
 *     {
 *         $this->analytics->track('vat_request', [
 *             'operation' => $operation,
 *             'duration_ms' => $duration * 1000,
 *             'countries' => $context['member_states'] ?? [],
 *             'date' => $context['situation_on'] ?? null,
 *         ]);
 *     }
 * }
 * ```
 *
 * @package Netresearch\EuVatSdk\Telemetry
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
interface TelemetryInterface
{
    /**
     * Record successful completion of an SDK operation
     *
     * This method is called when an SDK operation completes successfully,
     * providing timing and contextual information for performance monitoring.
     *
     * @param string               $operation The operation name (e.g., 'retrieveVatRates')
     * @param float                $duration  Duration of the operation in seconds (with microsecond precision)
     * @param array<string, mixed> $context   Additional context data from the operation
     *                                      - 'member_states': array of country codes requested
     *                                      - 'situation_on': DateTimeInterface of the request date
     *                                      - 'result_count': int number of results returned
     *                                      - 'endpoint': string service endpoint used
     *
     * @example Typical usage in client implementation:
     * ```php
     * $startTime = microtime(true);
     * try {
     *     $response = $soapClient->call($method, $arguments);
     *     $duration = microtime(true) - $startTime;
     *
     *     $this->telemetry->recordRequest('retrieveVatRates', $duration, [
     *         'member_states' => $request->getMemberStates(),
     *         'situation_on' => $request->getSituationOn(),
     *         'result_count' => count($response->getResults()),
     *         'endpoint' => $this->config->endpoint,
     *     ]);
     * } catch (Exception $e) {
     *     // Handle error...
     * }
     * ```
     */
    public function recordRequest(string $operation, float $duration, array $context = []): void;

    /**
     * Record occurrence of an error during SDK operations
     *
     * This method is called when an SDK operation fails, providing information
     * about error frequency and types for monitoring and alerting.
     *
     * @param string               $operation The operation name that failed (e.g., 'retrieveVatRates')
     * @param string               $errorType The type of error that occurred (exception class name)
     * @param array<string, mixed> $context   Additional context data about the error
     *                                      - 'member_states': array of country codes requested
     *                                      - 'situation_on': DateTimeInterface of the request date
     *                                      - 'error_message': string error message
     *                                      - 'error_code': string|int service error code (if available)
     *                                      - 'endpoint': string service endpoint used
     *                                      - 'duration': float time elapsed before error occurred
     *
     * @example Typical usage in error handling:
     * ```php
     * } catch (InvalidRequestException $e) {
     *     $this->telemetry->recordError('retrieveVatRates', 'InvalidRequestException', [
     *         'member_states' => $request->getMemberStates(),
     *         'error_message' => $e->getMessage(),
     *         'error_code' => $e->getErrorCode(),
     *         'endpoint' => $this->config->endpoint,
     *         'duration' => microtime(true) - $startTime,
     *     ]);
     *     throw $e;
     * }
     * ```
     */
    public function recordError(string $operation, string $errorType, array $context = []): void;
}
