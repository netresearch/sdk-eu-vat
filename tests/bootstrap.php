<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Brick\Math\BigDecimal;
use PHPStan\Rules\Rule;

/**
 * PHPUnit bootstrap file for EU VAT SDK tests
 *
 * This file is loaded before any tests run and provides common setup
 * for both unit and integration tests.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

// Ensure autoloader is available
$autoloader = require __DIR__ . '/../vendor/autoload.php';
// Set timezone to avoid warnings
date_default_timezone_set('UTC');

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Memory limit for tests
ini_set('memory_limit', '1G');

// Test environment variables
if (!getenv('APP_ENV')) {
    putenv('APP_ENV=test');
}

// Integration test defaults
if (!getenv('USE_PRODUCTION_ENDPOINT')) {
    putenv('USE_PRODUCTION_ENDPOINT=false');
}

if (!getenv('REFRESH_CASSETTES')) {
    putenv('REFRESH_CASSETTES=false');
}

if (!getenv('DEBUG_TESTS')) {
    putenv('DEBUG_TESTS=false');
}

// Ensure test directories exist
$testDirs = [
    __DIR__ . '/fixtures/cassettes',
    __DIR__ . '/../var/cache',
    __DIR__ . '/../var/logs',
];

foreach ($testDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Custom assertion helpers
if (!function_exists('assertValidEuCountryCode')) {
    /**
     * Assert that a string is a valid EU country code
     */
    function assertValidEuCountryCode(string $countryCode): void
    {
        $validCodes = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        Assert::assertContains(
            $countryCode,
            $validCodes,
            "'{$countryCode}' is not a valid EU member state code"
        );
    }
}

if (!function_exists('assertValidVatRate')) {
    /**
     * Assert that a value represents a valid VAT rate
     */
    function assertValidVatRate(string $rate): void
    {
        Assert::assertMatchesRegularExpression(
            '/^\d+\.\d+$/',
            $rate,
            "'{$rate}' is not a valid VAT rate format"
        );

        $rateDecimal = BigDecimal::of($rate);

        Assert::assertTrue(
            $rateDecimal->isGreaterThanOrEqualTo('0'),
            "VAT rate cannot be negative"
        );

        Assert::assertTrue(
            $rateDecimal->isLessThanOrEqualTo('50.0'),
            "VAT rate seems unreasonably high"
        );
    }
}

// Register custom PHPStan rules if available
if (class_exists(Rule::class)) {
    // Custom rules would be registered here
}

// Output test environment info
if (getenv('DEBUG_TESTS') === 'true') {
    echo "Test Environment Initialized:\n";
    echo "- PHP Version: " . PHP_VERSION . "\n";
    echo "- Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "- Timezone: " . date_default_timezone_get() . "\n";
    echo "- Use Production Endpoint: " . (getenv('USE_PRODUCTION_ENDPOINT') ?: 'false') . "\n";
    echo "- Refresh Cassettes: " . (getenv('REFRESH_CASSETTES') ?: 'false') . "\n";
    echo "\n";
}
