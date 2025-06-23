<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\EventListener;

use Netresearch\EuVatSdk\Engine\SoapResponseEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener for logging SOAP responses
 *
 * This listener provides comprehensive logging of SOAP responses for debugging,
 * monitoring, and audit purposes. It captures response timing, size information,
 * and content analysis while respecting debug vs production logging levels.
 *
 * Response logging operates at two levels:
 * - INFO level: Response summary and timing for production monitoring
 * - DEBUG level: Detailed response analysis including size and structure
 *
 * @example Integration with SOAP operations:
 * ```php
 * $listener = new ResponseEventListener($logger, $isDebug);
 * $listener->logResponse('retrieveVatRates', $response, $startTime, $endTime);
 * ```
 *
 * @package Netresearch\EuVatSdk\EventListener
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class ResponseEventListener implements EventSubscriberInterface
{
    /**
     * Create response event listener
     *
     * @param LoggerInterface $logger PSR-3 logger implementation.
     * @param boolean         $debug  Enable debug mode for detailed logging.
     */
    public function __construct(private readonly LoggerInterface $logger, private readonly bool $debug = false)
    {
    }

    /**
     * Get subscribed events for Symfony EventDispatcher
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SoapResponseEvent::NAME => 'onSoapResponse',
        ];
    }

    /**
     * Handle SOAP response event
     *
     * @param SoapResponseEvent $event The response event from EventAwareEngine
     */
    public function onSoapResponse(SoapResponseEvent $event): void
    {
        $this->logResponse(
            $event->getMethod(),
            $event->getResult(),
            $event->getStartTime(),
            $event->getEndTime()
        );
    }

    /**
     * Log SOAP response information
     *
     * Records response details at appropriate log levels. In debug mode,
     * includes detailed response analysis. In production mode, focuses
     * on essential metrics and timing information.
     *
     * @param string               $method    SOAP method that was called.
     * @param mixed                $response  Response data from SOAP service.
     * @param float                $startTime Request start time from microtime(true).
     * @param float                $endTime   Response received time from microtime(true).
     * @param array<string, mixed> $context   Additional context information.
     *
     * @example Logging a successful VAT rates response:
     * ```php
     * $listener->logResponse('retrieveVatRates', $vatRatesResponse, $startTime, $endTime, [
     *     'correlation_id' => 'req_123',
     *     'countries_requested' => ['DE', 'FR']
     * ]);
     * ```
     *
     */
    public function logResponse(
        string $method,
        mixed $response,
        float $startTime,
        float $endTime,
        array $context = []
    ): void {
        $duration = ($endTime - $startTime) * 1000; // milliseconds

        $baseContext = [
            'method' => $method,
            'duration_ms' => round($duration, 2),
            'response_time' => date('c', (int) $endTime),
            'response_type' => $this->getResponseType($response),
        ] + $context;

        if ($this->debug) {
            // Debug mode: Detailed response analysis
            $debugContext = $baseContext + [
                'response_size_bytes' => $this->calculateResponseSize($response),
                'response_structure' => $this->analyzeResponseStructure($response),
                'memory_usage_after' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ];

            $this->logger->debug('EU VAT SOAP Response received with analysis', $debugContext);
            return;
        }

        // Production mode: Essential metrics only
        if ($duration > 5000) {
            // Log slow responses as warnings
            $this->logger->warning('Slow EU VAT SOAP Response detected', $baseContext);
            return;
        }

        $this->logger->info('EU VAT SOAP Response received', $baseContext);
    }

    /**
     * Log response error information
     *
     * Records details when a response indicates an error condition,
     * even if the HTTP/SOAP layer succeeded.
     *
     * @param string               $method    SOAP method that was called.
     * @param mixed                $response  Error response from service.
     * @param string               $errorType Type of error detected.
     * @param float                $duration  Request duration in milliseconds.
     * @param array<string, mixed> $context   Additional context.
     *
     */
    public function logResponseError(
        string $method,
        mixed $response,
        string $errorType,
        float $duration,
        array $context = []
    ): void {
        $errorContext = [
            'method' => $method,
            'error_type' => $errorType,
            'duration_ms' => round($duration, 2),
            'response_type' => $this->getResponseType($response),
        ] + $context;

        if ($this->debug && $response !== null) {
            $errorContext['error_response'] = $this->sanitizeErrorResponse($response);
        }

        $this->logger->error('EU VAT SOAP Response contains error', $errorContext);
    }

    /**
     * Log response performance metrics
     *
     * Records detailed performance information for monitoring and
     * optimization purposes. Useful for identifying performance trends.
     *
     * @param string               $method       SOAP method name.
     * @param float                $duration     Total request duration in milliseconds.
     * @param integer              $responseSize Response size in bytes.
     * @param array<string, mixed> $metrics      Additional performance metrics.
     *
     */
    public function logPerformanceMetrics(
        string $method,
        float $duration,
        int $responseSize,
        array $metrics = []
    ): void {
        $performanceContext = [
            'method' => $method,
            'duration_ms' => round($duration, 2),
            'response_size_bytes' => $responseSize,
            'throughput_bytes_per_ms' => $duration > 0 ? round($responseSize / $duration, 2) : 0,
        ] + $metrics;

        // Categorize performance
        if ($duration > 10000) {
            $this->logger->warning('Very slow EU VAT response detected', $performanceContext);
        } elseif ($duration > 5000) {
            $this->logger->notice('Slow EU VAT response detected', $performanceContext);
        } elseif ($this->debug) {
            $this->logger->debug('EU VAT response performance metrics', $performanceContext);
        }
    }

    /**
     * Get response type for logging
     *
     * @param mixed $response Response object or data.
     * @return string Human-readable response type.
     */
    private function getResponseType(mixed $response): string
    {
        if ($response === null) {
            return 'null';
        }

        if (is_object($response)) {
            $className = $response::class;
            // Simplify namespaced class names for readability
            $shortName = strrchr($className, '\\');
            return $shortName !== false ? substr($shortName, 1) : $className;
        }

        return gettype($response);
    }

    /**
     * Calculate response size for monitoring
     *
     * @param mixed $response Response data.
     * @return integer Estimated size in bytes.
     */
    private function calculateResponseSize(mixed $response): int
    {
        if ($response === null) {
            return 0;
        }

        // For objects, try to serialize to estimate size
        if (is_object($response)) {
            try {
                return strlen(serialize($response));
            } catch (\Throwable) {
                // Fallback: estimate based on object type
                return strlen($response::class) + 100; // rough estimate
            }
        }

        // For arrays and scalars
        if (is_array($response)) {
            return strlen(serialize($response));
        }

        return strlen((string) $response);
    }

    /**
     * Analyze response structure for debugging
     *
     * @param mixed $response Response data.
     * @return array<string, mixed> Structure analysis.
     */
    private function analyzeResponseStructure(mixed $response): array
    {
        if ($response === null) {
            return ['type' => 'null'];
        }

        if (is_object($response)) {
            $analysis = [
                'type' => 'object',
                'class' => $response::class,
                'public_properties' => count(get_object_vars($response)),
            ];

            // For DTO objects, try to count elements
            if (method_exists($response, 'getResults') && is_callable([$response, 'getResults'])) {
                try {
                    $results = $response->getResults();
                    $analysis['result_count'] = is_countable($results) ? count($results) : 'unknown';
                } catch (\Throwable) {
                    $analysis['result_count'] = 'error_getting_count';
                }
            }

            return $analysis;
        }

        if (is_array($response)) {
            return [
                'type' => 'array',
                'element_count' => count($response),
                'keys' => array_keys($response),
            ];
        }

        return [
            'type' => gettype($response),
            'length' => is_string($response) ? strlen($response) : null,
        ];
    }

    /**
     * Sanitize error response for safe logging
     *
     * @param mixed $response Error response data.
     * @return mixed Sanitized response safe for logging.
     */
    private function sanitizeErrorResponse(mixed $response): mixed
    {
        // For EU VAT service, responses typically don't contain sensitive data
        // But we'll limit the size to prevent log explosion
        if (is_string($response) && strlen($response) > 1000) {
            return substr($response, 0, 1000) . '... [TRUNCATED]';
        }

        if (is_array($response) && count($response) > 20) {
            $truncated = array_slice($response, 0, 20, true);
            $truncated['[TRUNCATED]'] = sprintf('... and %d more elements', count($response) - 20);
            return $truncated;
        }

        return $response;
    }


    /**
     * Check if debug logging is enabled
     *
     * @return boolean True if debug mode is active.
     */
    public function isDebugEnabled(): bool
    {
        return $this->debug;
    }
}
