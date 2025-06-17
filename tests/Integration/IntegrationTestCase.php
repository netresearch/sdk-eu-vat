<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Integration;

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use PHPUnit\Framework\TestCase;
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

        // Turn on VCR for this test
        VCR::turnOn();

        // Initialize the client
        $this->initializeClient();
    }

    /**
     * Clean up VCR after each test
     */
    protected function tearDown(): void
    {
        // Always eject any active cassette if we have one
        if ($this->cassetteName !== null) {
            VCR::eject();
            $this->cassetteName = null;
        }

        // Turn off VCR for this test
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
        // Use sandbox endpoint by default for integration tests
        $useProductionEndpoint = getenv('USE_PRODUCTION_ENDPOINT') === 'true';

        if ($useProductionEndpoint) {
            // Create sandbox client but with production endpoint for testing
            $config = ClientConfiguration::test(null)
                ->withEndpoint(ClientConfiguration::ENDPOINT_PRODUCTION)
                ->withTimeout(15)
                ->withDebug(true);
            $this->client = new SoapVatRetrievalClient($config);
        } else {
            $this->client = VatRetrievalClientFactory::createSandbox();
        }
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

        VCR::insertCassette($cassetteName);
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
     * @param string|null $cassetteName Name of the cassette file (null for auto-generated)
     */
    protected function setupVcr(?string $cassetteName = null): void
    {
        $cassetteName ??= $this->getDefaultCassetteName();

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
     * @param string $environment Environment name (production or sandbox)
     * @return VatRetrievalClientInterface Configured client
     */
    protected function getClientForEnvironment(string $environment): VatRetrievalClientInterface
    {
        return match (strtolower($environment)) {
            'production' => VatRetrievalClientFactory::create(),
            'sandbox', 'test' => VatRetrievalClientFactory::createSandbox(),
            default => throw new \InvalidArgumentException(
                "Unsupported environment: {$environment}. Use 'production' or 'sandbox'."
            )
        };
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

    /**
     * Generate a unique cassette name based on test class and method
     *
     * @return string Unique cassette name for the current test
     */
    protected function getDefaultCassetteName(): string
    {
        // Simple fallback - use class name and timestamp
        $className = (new \ReflectionClass($this))->getShortName();
        $className = str_replace('Test', '', $className);

        return strtolower($className) . '/default_test_' . time();
    }
}
