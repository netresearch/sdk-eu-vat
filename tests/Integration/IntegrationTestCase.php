<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use VCR\VCR;

/**
 * Base class for integration tests with php-vcr support
 *
 * This abstract test case provides VCR setup and teardown for recording and
 * replaying SOAP interactions with the EU VAT Retrieval Service. It ensures
 * tests can run reliably offline after initial cassette recording.
 *
 * @package Netresearch\EuVatSdk\Tests\Integration
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * VAT retrieval client instance
     */
    protected VatRetrievalClientInterface $client;

    /**
     * Logger instance for debugging
     */
    protected LoggerInterface $logger;

    /**
     * Current VCR cassette name
     */
    protected ?string $cassetteName = null;

    /**
     * Set up VCR and client before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load VCR configuration
        require_once __DIR__ . '/../fixtures/vcr-config.php';

        // Turn on VCR
        VCR::turnOn();

        // Create a test logger that outputs to stderr for debugging
        $this->logger = new class implements LoggerInterface {
            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->log('EMERGENCY', $message, $context);
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->log('ALERT', $message, $context);
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->log('CRITICAL', $message, $context);
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->log('ERROR', $message, $context);
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->log('WARNING', $message, $context);
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->log('NOTICE', $message, $context);
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->log('INFO', $message, $context);
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                if (getenv('DEBUG_TESTS')) {
                    $this->log('DEBUG', $message, $context);
                }
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                fwrite(STDERR, sprintf(
                    "[%s] %s: %s %s\n",
                    date('Y-m-d H:i:s'),
                    $level,
                    $message,
                    $context ? json_encode($context) : ''
                ));
            }
        };

        // Initialize the client
        $this->initializeClient();
    }

    /**
     * Clean up VCR after each test
     */
    protected function tearDown(): void
    {
        if ($this->cassetteName !== null) {
            VCR::eject();
            $this->cassetteName = null;
        }

        VCR::turnOff();

        parent::tearDown();
    }

    /**
     * Initialize the VAT retrieval client
     *
     * Override this method in test classes to customize client configuration
     */
    protected function initializeClient(): void
    {
        // Use test endpoint by default for integration tests
        $useTestEndpoint = getenv('USE_PRODUCTION_ENDPOINT') !== 'true';

        $this->client = VatRetrievalClientFactory::createForTesting(
            $this->logger,
            $useTestEndpoint
        );
    }

    /**
     * Insert a VCR cassette for recording/replaying
     *
     * @param string               $cassetteName Name of the cassette file (without extension)
     * @param array<string, mixed> $options      VCR options (e.g., record mode)
     */
    protected function insertCassette(string $cassetteName, array $options = []): void
    {
        $this->cassetteName = $cassetteName;

        // Default options
        $defaultOptions = [
            'record' => 'new_episodes', // Record new requests, replay existing ones
            'match_requests_on' => ['method', 'url', 'body'],
        ];

        $options = array_merge($defaultOptions, $options);

        VCR::insertCassette($cassetteName, $options);
    }

    /**
     * Force recording mode for refreshing cassettes
     *
     * Use this when you need to update cassettes with fresh API responses
     *
     * @param string $cassetteName Name of the cassette file
     */
    protected function recordCassette(string $cassetteName): void
    {
        $this->insertCassette($cassetteName, ['record' => 'all']);
    }

    /**
     * Sets up a VCR cassette, respecting the REFRESH_CASSETTES environment variable
     *
     * @param string $cassetteName Name of the cassette file
     */
    protected function setupVcr(string $cassetteName): void
    {
        if ($this->shouldRefreshCassettes()) {
            $this->recordCassette($cassetteName);
        } else {
            $this->insertCassette($cassetteName);
        }
    }

    /**
     * Check if we're in cassette refresh mode
     *
     * @return boolean True if REFRESH_CASSETTES env var is set
     */
    protected function shouldRefreshCassettes(): bool
    {
        return getenv('REFRESH_CASSETTES') === 'true';
    }

    /**
     * Get a client configured for a specific environment
     *
     * @param string $environment Environment name (production, test, etc.)
     * @return VatRetrievalClientInterface Configured client
     */
    protected function getClientForEnvironment(string $environment): VatRetrievalClientInterface
    {
        return VatRetrievalClientFactory::createForEnvironment(
            $environment,
            $this->logger
        );
    }

    /**
     * Assert that a date string matches the expected EU format (YYYY-MM-DD)
     *
     * @param string $dateString Date string to validate
     * @param string $message    Optional failure message
     */
    protected function assertValidEuDateFormat(string $dateString, string $message = ''): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $dateString,
            $message ?: "Date '$dateString' does not match EU format YYYY-MM-DD"
        );
    }

    /**
     * Assert that a decimal value has the expected precision
     *
     * @param string  $value             Decimal value as string
     * @param integer $expectedPrecision Expected decimal places
     * @param string  $message           Optional failure message
     */
    protected function assertDecimalPrecision(string $value, int $expectedPrecision, string $message = ''): void
    {
        $parts = explode('.', $value);
        $actualPrecision = isset($parts[1]) ? strlen($parts[1]) : 0;

        $this->assertEquals(
            $expectedPrecision,
            $actualPrecision,
            $message ?: "Expected precision of $expectedPrecision, got $actualPrecision for value '$value'"
        );
    }
}
