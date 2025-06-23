<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\EventListener;

use Netresearch\EuVatSdk\Engine\SoapRequestEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener for logging outgoing SOAP requests
 *
 * This listener provides detailed logging of SOAP requests for debugging
 * and audit purposes. It captures request timing, method information,
 * and sanitized request arguments.
 *
 * The listener operates at two levels:
 * - INFO level: Basic request information suitable for production
 * - DEBUG level: Detailed request payload for troubleshooting
 *
 * @example Integration with SOAP operations:
 * ```php
 * $listener = new RequestEventListener($logger, $isDebug);
 * $listener->logRequest('retrieveVatRates', $arguments, microtime(true));
 * ```
 *
 * @package Netresearch\EuVatSdk\EventListener
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class RequestEventListener implements EventSubscriberInterface
{
    /**
     * Create request event listener
     *
     * @param LoggerInterface $logger PSR-3 logger implementation.
     * @param boolean         $debug  Enable debug mode for verbose logging.
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
            SoapRequestEvent::NAME => 'onSoapRequest',
        ];
    }

    /**
     * Handle SOAP request event
     *
     * @param SoapRequestEvent $event The request event from EventAwareEngine
     */
    public function onSoapRequest(SoapRequestEvent $event): void
    {
        $startTime = microtime(true);
        $this->logRequest($event->getMethod(), $event->getArguments(), $startTime);
    }

    /**
     * Log SOAP request information
     *
     * Records request details at appropriate log levels based on debug mode.
     * In debug mode, includes full request arguments for troubleshooting.
     *
     * @param string               $method    SOAP method being called (e.g., 'retrieveVatRates').
     * @param array<string, mixed> $arguments Request arguments.
     * @param float                $startTime Request start time from microtime(true).
     * @param array<string, mixed> $context   Additional context information.
     *
     * @example Logging a VAT rates request:
     * ```php
     * $startTime = microtime(true);
     * $listener->logRequest('retrieveVatRates', [
     *     'memberStates' => ['DE', 'FR'],
     *     'situationOn' => '2024-01-15'
     * ], $startTime, ['correlation_id' => 'req_123']);
     * ```
     *
     */
    public function logRequest(
        string $method,
        array $arguments,
        float $startTime,
        array $context = []
    ): void {
        $baseContext = [
            'method' => $method,
            'request_time' => date('c', (int) $startTime),
            'arguments_count' => count($arguments),
        ] + $context;

        if ($this->debug) {
            // Debug mode: Log detailed request information
            $debugContext = $baseContext + [
                'arguments' => $this->sanitizeArguments($arguments),
                'memory_usage' => memory_get_usage(true),
            ];

            $this->logger->debug('EU VAT SOAP Request initiated with detailed payload', $debugContext);
            return;
        }

        // Production mode: Log essential information only
        $this->logger->info('EU VAT SOAP Request initiated', $baseContext);
    }

    /**
     * Log request timing information
     *
     * Records the time taken to prepare and send a request, useful for
     * performance monitoring and identifying slow request preparation.
     *
     * @param string               $method       SOAP method name.
     * @param float                $startTime    Request start time from microtime(true).
     * @param float                $preparedTime Time when request was prepared.
     * @param array<string, mixed> $context      Additional context.
     *
     */
    public function logRequestTiming(
        string $method,
        float $startTime,
        float $preparedTime,
        array $context = []
    ): void {
        $preparationTime = ($preparedTime - $startTime) * 1000; // milliseconds

        $timingContext = [
            'method' => $method,
            'preparation_time_ms' => round($preparationTime, 2),
        ] + $context;

        if ($preparationTime > 100) {
            // Log slow request preparation as warning
            $this->logger->warning('Slow SOAP request preparation detected', $timingContext);
        } elseif ($this->debug) {
            $this->logger->debug('SOAP request preparation timing', $timingContext);
        }
    }

    /**
     * Sanitize request arguments for safe logging
     *
     * Removes or masks sensitive information from request arguments
     * before logging. Currently focused on EU VAT service which doesn't
     * contain sensitive data, but provides structure for future enhancement.
     *
     * @param array<string, mixed> $arguments Raw request arguments.
     * @return array<string, mixed> Sanitized arguments safe for logging.
     */
    private function sanitizeArguments(array $arguments): array
    {
        // For EU VAT service, the arguments (country codes, dates) are not sensitive
        // But we'll limit array sizes to prevent log explosion
        return $this->limitArrayDepth($arguments, 3, 50);
    }

    /**
     * Limit array depth and size for safe logging
     *
     * Prevents log explosion from deeply nested or very large arrays
     * by truncating at specified depth and size limits.
     *
     * @param mixed   $data        Data to limit.
     * @param integer $maxDepth    Maximum nesting depth.
     * @param integer $maxElements Maximum elements per array.
     * @return mixed Limited data structure.
     */
    private function limitArrayDepth(mixed $data, int $maxDepth, int $maxElements): mixed
    {
        if ($maxDepth <= 0) {
            return '[MAX_DEPTH_REACHED]';
        }

        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        $count = 0;

        foreach ($data as $key => $value) {
            if ($count >= $maxElements) {
                $result['[TRUNCATED]'] = sprintf(
                    '... and %d more elements',
                    count($data) - $maxElements
                );
                break;
            }

            $result[$key] = $this->limitArrayDepth($value, $maxDepth - 1, $maxElements);
            $count++;
        }

        return $result;
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
