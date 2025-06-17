<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Middleware;

use Netresearch\EuVatSdk\Telemetry\TelemetryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Middleware for comprehensive SOAP request/response logging and telemetry
 *
 * This middleware provides cross-cutting concerns for SOAP operations including:
 * - Request/response timing and performance monitoring
 * - High-level logging suitable for production environments
 * - Telemetry integration for observability platforms
 * - Error tracking and categorization
 *
 * Unlike event listeners which handle specific granular events, this middleware
 * wraps the entire SOAP operation to provide end-to-end monitoring and logging.
 *
 * @example Integration with SOAP client:
 * ```php
 * $middleware = new LoggingMiddleware($logger, $telemetry);
 * $result = $middleware->process('retrieveVatRates', $arguments, function($method, $args) {
 *     return $this->soapClient->call($method, $args);
 * });
 * ```
 *
 * @package Netresearch\EuVatSdk\Middleware
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class LoggingMiddleware
{
    /**
     * PSR-3 logger for operation recording
     */
    private LoggerInterface $logger;

    /**
     * Telemetry interface for metrics recording
     */
    private TelemetryInterface $telemetry;

    /**
     * Create logging middleware
     *
     * @param LoggerInterface    $logger    PSR-3 logger implementation.
     * @param TelemetryInterface $telemetry Telemetry implementation for metrics.
     */
    public function __construct(LoggerInterface $logger, TelemetryInterface $telemetry)
    {
        $this->logger = $logger;
        $this->telemetry = $telemetry;
    }

    /**
     * Process SOAP operation with comprehensive logging and telemetry
     *
     * Wraps the SOAP operation with timing, logging, and telemetry recording.
     * Handles both successful operations and errors, ensuring all metrics
     * are captured appropriately.
     *
     * @param string               $method    SOAP method being called.
     * @param array<string, mixed> $arguments Method arguments.
     * @param callable             $next      Next handler in the chain (actual SOAP call).
     * @return mixed Result from the SOAP operation.
     * @throws Throwable Re-throws any exceptions after logging/telemetry.
     *
     * @example Usage in SOAP client:
     * ```php
     * public function retrieveVatRates(VatRatesRequest $request): VatRatesResponse
     * {
     *     return $this->middleware->process(
     *         'retrieveVatRates',
     *         $this->prepareArguments($request),
     *         fn($method, $args) => $this->soapEngine->call($method, $args)
     *     );
     * }
     * ```
     */
    public function process(string $method, array $arguments, callable $next): mixed
    {
        $startTime = hrtime(true);
        $correlationId = $this->generateCorrelationId();

        // Log operation initiation
        $this->logger->info('EU VAT SOAP Operation initiated', [
            'method' => $method,
            'correlation_id' => $correlationId,
            'argument_count' => count($arguments),
            'started_at' => date('c'),
        ]);

        try {
            // Execute the actual SOAP operation
            $result = $next($method, $arguments);

            // Calculate timing
            $endTime = hrtime(true);
            $duration = ($endTime - $startTime) / 1e9; // Convert to seconds

            // Log successful completion
            $this->logSuccess($method, $correlationId, $duration, $result, $arguments);

            // Record telemetry for successful operation
            try {
                $this->recordSuccessMetrics($method, $duration, $arguments, $result);
            } catch (Throwable $telemetryError) {
                // Log telemetry error but don't fail the operation
                $this->logger->warning('Telemetry error during successful operation', [
                    'telemetry_error' => $telemetryError->getMessage(),
                    'method' => $method,
                ]);
            }

            return $result;
        } catch (Throwable $exception) {
            // Calculate timing up to error point
            $errorTime = hrtime(true);
            $duration = ($errorTime - $startTime) / 1e9; // Convert to seconds

            // Log error details
            $this->logError($method, $correlationId, $duration, $exception, $arguments);

            // Record telemetry for failed operation (don't let telemetry errors mask original exception)
            try {
                $this->recordErrorMetrics($method, $duration, $exception, $arguments);
            } catch (Throwable $telemetryError) {
                // Log telemetry error but don't let it interfere with original exception
                $this->logger->warning('Telemetry error during operation failure', [
                    'telemetry_error' => $telemetryError->getMessage(),
                    'original_exception' => $exception->getMessage(),
                ]);
            }

            // Re-throw exception to maintain normal error flow
            throw $exception;
        }
    }

    /**
     * Log successful operation completion
     *
     * @param string               $method        SOAP method name.
     * @param string               $correlationId Request correlation identifier.
     * @param float                $duration      Operation duration in seconds.
     * @param mixed                $result        Operation result.
     * @param array<string, mixed> $arguments     Original arguments.
     *
     */
    private function logSuccess(
        string $method,
        string $correlationId,
        float $duration,
        mixed $result,
        array $arguments
    ): void {
        $context = [
            'method' => $method,
            'correlation_id' => $correlationId,
            'duration_ms' => round($duration * 1000, 2),
            'result_type' => $this->getResultType($result),
            'completed_at' => date('c'),
        ];

        // Add result-specific context for known response types
        if (is_object($result) && method_exists($result, 'getResults')) {
            try {
                $results = $result->getResults();
                $context['result_count'] = is_countable($results) ? count($results) : 'unknown';
            } catch (Throwable) {
                // Ignore errors when trying to get result count
            }
        }

        // Performance categorization
        $durationMs = $duration * 1000;
        if ($durationMs > 10000) {
            $this->logger->warning('EU VAT SOAP Operation completed (very slow)', $context);
        } elseif ($durationMs > 5000) {
            $this->logger->notice('EU VAT SOAP Operation completed (slow)', $context);
        } else {
            $this->logger->info('EU VAT SOAP Operation completed successfully', $context);
        }
    }

    /**
     * Log error during operation
     *
     * @param string               $method        SOAP method name.
     * @param string               $correlationId Request correlation identifier.
     * @param float                $duration      Duration until error in seconds.
     * @param Throwable            $exception     Exception that occurred.
     * @param array<string, mixed> $arguments     Original arguments.
     *
     */
    private function logError(
        string $method,
        string $correlationId,
        float $duration,
        Throwable $exception,
        array $arguments
    ): void {
        $context = [
            'method' => $method,
            'correlation_id' => $correlationId,
            'duration_ms' => round($duration * 1000, 2),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'failed_at' => date('c'),
        ];

        // Add file and line for debugging (not in production due to path disclosure)
        if (getenv('APP_ENV') === 'development') {
            $context['exception_file'] = $exception->getFile();
            $context['exception_line'] = $exception->getLine();
        }

        $this->logger->error('EU VAT SOAP Operation failed', $context);
    }

    /**
     * Record telemetry metrics for successful operations
     *
     * @param string               $method    SOAP method name.
     * @param float                $duration  Operation duration in seconds.
     * @param array<string, mixed> $arguments Original arguments.
     * @param mixed                $result    Operation result.
     *
     */
    private function recordSuccessMetrics(
        string $method,
        float $duration,
        array $arguments,
        mixed $result
    ): void {
        $context = [
            'endpoint' => 'EU_VAT_Service', // Could be made configurable
            'result_type' => $this->getResultType($result),
        ];

        // Add method-specific context
        if ($method === 'retrieveVatRates' && isset($arguments['memberStates'])) {
            $memberStates = $arguments['memberStates'];
            $context['member_states'] = is_array($memberStates) ? $memberStates : [$memberStates];
            $context['country_count'] = is_array($memberStates) ? count($memberStates) : 1;
        }

        $this->telemetry->recordRequest($method, $duration, $context);
    }

    /**
     * Record telemetry metrics for failed operations
     *
     * @param string               $method    SOAP method name.
     * @param float                $duration  Duration until failure in seconds.
     * @param Throwable            $exception Exception that occurred.
     * @param array<string, mixed> $arguments Original arguments.
     *
     */
    private function recordErrorMetrics(
        string $method,
        float $duration,
        Throwable $exception,
        array $arguments
    ): void {
        $context = [
            'endpoint' => 'EU_VAT_Service',
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'duration' => $duration,
        ];

        // Add method-specific context for error analysis
        if ($method === 'retrieveVatRates' && isset($arguments['memberStates'])) {
            $memberStates = $arguments['memberStates'];
            $context['member_states'] = is_array($memberStates) ? $memberStates : [$memberStates];
        }

        $this->telemetry->recordError($method, get_class($exception), $context);
    }

    /**
     * Get result type for logging and telemetry
     *
     * @param mixed $result Operation result.
     * @return string Human-readable result type.
     */
    private function getResultType(mixed $result): string
    {
        if ($result === null) {
            return 'null';
        }

        if (is_object($result)) {
            $className = get_class($result);
            // Simplify namespaced class names for readability
            $shortName = strrchr($className, '\\');
            return $shortName !== false ? substr($shortName, 1) : $className;
        }

        return gettype($result);
    }

    /**
     * Generate unique correlation ID for request tracking
     *
     * @return string Unique correlation identifier
     */
    private function generateCorrelationId(): string
    {
        return sprintf(
            'vat_%s_%s',
            date('Ymd_His'),
            bin2hex(random_bytes(4))
        );
    }

    /**
     * Get logger instance for external access
     *
     * @return LoggerInterface Current logger instance
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get telemetry instance for external access
     *
     * @return TelemetryInterface Current telemetry instance
     */
    public function getTelemetry(): TelemetryInterface
    {
        return $this->telemetry;
    }
}
