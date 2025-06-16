<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Fixtures;

/**
 * Test data provider for EU VAT SDK tests
 * 
 * Provides realistic test data including EU member states, VAT rates,
 * edge cases, and boundary conditions for comprehensive testing.
 * 
 * @package Netresearch\EuVatSdk\Tests\Fixtures
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
class TestDataProvider
{
    /**
     * Current EU member states (as of 2024)
     * 
     * @var array<string>
     */
    public const EU_MEMBER_STATES = [
        'AT', // Austria
        'BE', // Belgium
        'BG', // Bulgaria
        'HR', // Croatia
        'CY', // Cyprus
        'CZ', // Czech Republic
        'DK', // Denmark
        'EE', // Estonia
        'FI', // Finland
        'FR', // France
        'DE', // Germany
        'GR', // Greece
        'HU', // Hungary
        'IE', // Ireland
        'IT', // Italy
        'LV', // Latvia
        'LT', // Lithuania
        'LU', // Luxembourg
        'MT', // Malta
        'NL', // Netherlands
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SK', // Slovakia
        'SI', // Slovenia
        'ES', // Spain
        'SE', // Sweden
    ];
    
    /**
     * Known standard VAT rates by country (as of 2024)
     * Used for validation in tests
     * 
     * @var array<string, float>
     */
    public const STANDARD_VAT_RATES = [
        'AT' => 20.0,
        'BE' => 21.0,
        'BG' => 20.0,
        'HR' => 25.0,
        'CY' => 19.0,
        'CZ' => 21.0,
        'DK' => 25.0,
        'EE' => 22.0,
        'FI' => 24.0,
        'FR' => 20.0,
        'DE' => 19.0,
        'GR' => 24.0,
        'HU' => 27.0, // Highest in EU
        'IE' => 23.0,
        'IT' => 22.0,
        'LV' => 21.0,
        'LT' => 21.0,
        'LU' => 17.0, // Lowest standard rate
        'MT' => 18.0,
        'NL' => 21.0,
        'PL' => 23.0,
        'PT' => 23.0,
        'RO' => 19.0,
        'SK' => 20.0,
        'SI' => 22.0,
        'ES' => 21.0,
        'SE' => 25.0,
    ];
    
    /**
     * Former EU member states
     * 
     * @var array<string, string>
     */
    public const FORMER_EU_STATES = [
        'GB' => '2020-12-31', // UK left on Brexit transition end
    ];
    
    /**
     * Invalid country codes for error testing
     * 
     * @var array<string>
     */
    public const INVALID_COUNTRY_CODES = [
        'XX', 'YY', 'ZZ', // Completely invalid
        'US', 'CN', 'JP', // Valid ISO but non-EU
        'AA', 'QQ',       // Reserved codes
        '',   '  ',       // Empty/whitespace
        'DEU', 'FRA',     // ISO-3 instead of ISO-2
        'de', 'fr',       // Lowercase
    ];
    
    /**
     * Test dates for various scenarios
     * 
     * @return array<string, array{date: string, description: string}>
     */
    public static function getTestDates(): array
    {
        return [
            'current' => [
                'date' => '2024-01-01',
                'description' => 'Current date for standard testing',
            ],
            'brexit_before' => [
                'date' => '2020-01-01',
                'description' => 'Before Brexit - UK still in EU',
            ],
            'brexit_after' => [
                'date' => '2021-01-01',
                'description' => 'After Brexit transition - UK not in EU',
            ],
            'croatia_join' => [
                'date' => '2013-07-01',
                'description' => 'Croatia joined EU',
            ],
            'future' => [
                'date' => '2030-01-01',
                'description' => 'Future date for edge case testing',
            ],
            'far_past' => [
                'date' => '1990-01-01',
                'description' => 'Historical date before some EU expansions',
            ],
        ];
    }
    
    /**
     * Get country groups for batch testing
     * 
     * @return array<string, array<string>>
     */
    public static function getCountryGroups(): array
    {
        return [
            'benelux' => ['BE', 'NL', 'LU'],
            'nordic' => ['DK', 'FI', 'SE'],
            'baltic' => ['EE', 'LV', 'LT'],
            'mediterranean' => ['ES', 'IT', 'GR', 'CY', 'MT'],
            'central_europe' => ['DE', 'AT', 'CZ', 'SK', 'PL', 'HU'],
            'western_europe' => ['FR', 'IE', 'PT'],
            'eastern_europe' => ['BG', 'RO', 'HR', 'SI'],
            'high_vat' => ['DK', 'SE', 'FI', 'HR', 'HU'], // 24%+
            'low_vat' => ['LU', 'MT', 'CY', 'DE', 'RO'],  // Under 20%
        ];
    }
    
    /**
     * Get edge case scenarios for boundary testing
     * 
     * @return array<string, array{countries: array<string>, date: string, description: string}>
     */
    public static function getEdgeCaseScenarios(): array
    {
        return [
            'single_country' => [
                'countries' => ['DE'],
                'date' => '2024-01-01',
                'description' => 'Minimum valid request',
            ],
            'all_countries' => [
                'countries' => self::EU_MEMBER_STATES,
                'date' => '2024-01-01',
                'description' => 'Maximum country load',
            ],
            'leap_year' => [
                'countries' => ['FR', 'IT'],
                'date' => '2024-02-29',
                'description' => 'Leap year date handling',
            ],
            'year_boundary' => [
                'countries' => ['DE', 'FR'],
                'date' => '2023-12-31',
                'description' => 'End of year boundary',
            ],
            'new_year' => [
                'countries' => ['DE', 'FR'],
                'date' => '2024-01-01',
                'description' => 'Start of year boundary',
            ],
        ];
    }
    
    /**
     * Get decimal precision test cases
     * 
     * @return array<string, array{value: string, expected_precision: int}>
     */
    public static function getDecimalPrecisionCases(): array
    {
        return [
            'whole_number' => ['value' => '20.0', 'expected_precision' => 1],
            'one_decimal' => ['value' => '21.5', 'expected_precision' => 1],
            'two_decimals' => ['value' => '18.75', 'expected_precision' => 2],
            'high_precision' => ['value' => '19.625', 'expected_precision' => 3],
            'trailing_zeros' => ['value' => '17.00', 'expected_precision' => 2],
        ];
    }
    
    /**
     * Get performance test configurations
     * 
     * @return array<string, array{batch_size: int, iterations: int, description: string}>
     */
    public static function getPerformanceTestConfigs(): array
    {
        return [
            'small_batch' => [
                'batch_size' => 5,
                'iterations' => 100,
                'description' => 'Small batch high frequency',
            ],
            'medium_batch' => [
                'batch_size' => 15,
                'iterations' => 50,
                'description' => 'Medium batch moderate frequency',
            ],
            'large_batch' => [
                'batch_size' => 27,
                'iterations' => 20,
                'description' => 'Full EU batch lower frequency',
            ],
        ];
    }
    
    /**
     * Get sample VAT rate response for unit testing
     * 
     * @param string $country
     * @param string $rate
     * @param string $date
     * @return array<string, mixed>
     */
    public static function getSampleVatRateResponse(
        string $country = 'DE',
        string $rate = '19.0',
        string $date = '2024-01-01'
    ): array {
        return [
            'memberState' => $country,
            'situationOn' => $date,
            'vatRate' => [
                'type' => 'STANDARD',
                'value' => $rate,
            ],
        ];
    }
}