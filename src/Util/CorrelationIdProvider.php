<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Util;

use Ramsey\Uuid\Uuid;

/**
 * Provides correlation IDs for request tracing across distributed systems
 *
 * This provider implements a common pattern for microservices where correlation IDs
 * are propagated from upstream services when available, or generated when starting
 * a new trace. This enables end-to-end request tracing in distributed architectures.
 *
 * @package Netresearch\EuVatSdk\Util
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class CorrelationIdProvider
{
    /**
     * Common header names for correlation IDs in HTTP requests
     */
    private const CORRELATION_HEADERS = [
        'X-Request-ID',
        'X-Correlation-ID',
        'X-Trace-ID',
        'Request-ID',
        'Correlation-ID',
    ];

    /**
     * Get or generate a correlation ID for request tracing
     *
     * This method implements the following priority order:
     * 1. Use provided correlation ID if given
     * 2. Check for correlation ID in HTTP headers (if available)
     * 3. Generate a new UUID v4 as fallback
     *
     * @param string|null $providedId Optional correlation ID to use
     * @param array<string, string> $headers Optional HTTP headers to check for existing correlation ID
     * @return string Valid correlation ID for request tracing
     */
    public function provide(?string $providedId = null, array $headers = []): string
    {
        // 1. Use provided ID if valid
        if ($providedId !== null && $providedId !== '') {
            return trim($providedId);
        }

        // 2. Check for correlation ID in HTTP headers
        $existingId = $this->extractFromHeaders($headers);
        if ($existingId !== null) {
            return $existingId;
        }

        // 3. Generate new UUID v4 as fallback
        return $this->generate();
    }

    /**
     * Generate a new correlation ID using UUID v4
     *
     * @return string Cryptographically secure random UUID v4 string
     */
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Extract correlation ID from HTTP headers if present
     *
     * Checks common correlation ID header names used in microservices.
     * Returns the first valid correlation ID found.
     *
     * @param array<string, string> $headers HTTP headers to search
     * @return string|null Correlation ID if found, null otherwise
     */
    private function extractFromHeaders(array $headers): ?string
    {
        // Normalize header names to lowercase for case-insensitive matching
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        foreach (self::CORRELATION_HEADERS as $headerName) {
            $normalizedName = strtolower($headerName);
            if (isset($normalizedHeaders[$normalizedName])) {
                $value = trim($normalizedHeaders[$normalizedName]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
