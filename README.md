# EU VAT SOAP SDK

[![Quality Assurance](https://github.com/netresearch/sdk-eu-vat/actions/workflows/quality-assurance.yml/badge.svg)](https://github.com/netresearch/sdk-eu-vat/actions/workflows/quality-assurance.yml)
[![Coverage Status](https://codecov.io/gh/netresearch/sdk-eu-vat/branch/main/graph/badge.svg)](https://codecov.io/gh/netresearch/sdk-eu-vat)
[![Latest Stable Version](https://poser.pugx.org/netresearch/sdk-eu-vat/v/stable)](https://packagist.org/packages/netresearch/sdk-eu-vat)
[![License](https://poser.pugx.org/netresearch/sdk-eu-vat/license)](https://packagist.org/packages/netresearch/sdk-eu-vat)

A modern PHP 8.2+ SDK for the [EU VAT Retrieval Service](https://ec.europa.eu/taxation_customs/tedb/) that provides reliable access to official VAT rates for all EU member states, decoded into arbitrary-precision decimals rather than floats.

## Features

- 🏦 **Financial-Grade Precision**: Rate values are parsed straight from the XML text into `brick/math` BigDecimal — no PHP float is involved at any point, so the exact literal the service sent (including its scale, e.g. `17.0`) is what you get back
- 🛡️ **Typed Errors**: SOAP faults are classified into `InvalidRequestException` for a rejected request and `ServiceUnavailableException` for a failure that never reached the service
- 🧪 **Thoroughly Tested**: Comprehensive unit and integration test suites with real service validation
- 🔄 **Modern SOAP**: Built on `php-soap/ext-soap-engine` for reliable SOAP operations
- 📊 **Observability**: PSR-3 logging, plus timing and error metrics for any backend through `TelemetryInterface`
- 🚀 **Performance**: WSDL caching, and the WSDL is bundled so no schema fetch is needed at runtime
- 📖 **Well Documented**: Comprehensive PHPDoc and usage examples

## Installation

```bash
composer require netresearch/sdk-eu-vat
```

**Requirements:** PHP 8.2+, `ext-soap` and `ext-libxml` extensions

💡 **Having installation issues?** See the [Installation Guide](INSTALLATION.md) for troubleshooting help.

## Quick Start

```php
<?php

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;

// Create client
$client = VatRetrievalClientFactory::create();

// Request VAT rates for Germany
$request = new VatRatesRequest(
    memberStates: ['DE'],
    situationOn: new DateTime('2024-01-01')
);

try {
    $response = $client->retrieveVatRates($request);
    
    foreach ($response->getResults() as $result) {
        echo sprintf(
            "VAT rate for %s: %s%%\n",
            $result->getMemberState(),
            (string) $result->getRate()
        );
    }
} catch (\Netresearch\EuVatSdk\Exception\VatServiceException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Advanced Usage

### Multiple Countries

```php
$request = new VatRatesRequest(
    memberStates: ['DE', 'FR', 'IT', 'ES'],
    situationOn: new DateTime('2024-01-01')
);

$response = $client->retrieveVatRates($request);

// Group results by country
foreach ($response->getResults() as $result) {
    $rate = $result->getRate();

    // Some rate types carry no percentage at all — getValue() is null for those,
    // and casting the rate to string yields an empty string. Decide explicitly
    // what to display instead of printing an empty percentage.
    $display = $rate->getValue() === null ? 'n/a' : (string) $rate . '%';

    printf(
        "%s: %s (%s rate)\n",
        $result->getMemberState(),
        $display,
        $rate->getType()
    );
}
```

### Precision Financial Calculations

```php
use Brick\Math\BigDecimal;

$vatRate = $result->getRate();

// Get precise decimal value
$rate = $vatRate->getValue(); // Returns BigDecimal, or null for rate types without a value

$netAmount = BigDecimal::of('100.00');

if ($rate === null) {
    // No percentage was supplied for this rate type (e.g. exempt / out of scope).
    // Treat it as zero VAT rather than dereferencing the null value.
    echo "Net: €" . $netAmount->__toString() . " (no VAT, rate type: " . $vatRate->getType() . ")\n";
} else {
    // Calculate VAT amount (100 EUR at 19% VAT)
    $vatAmount = $netAmount->multipliedBy($rate)->dividedBy('100', 2);
    $grossAmount = $netAmount->plus($vatAmount);

    // Be explicit when printing
    echo "Net: €" . $netAmount->__toString() . "\n";
    echo "VAT: €" . $vatAmount->__toString() . "\n";
    echo "Gross: €" . $grossAmount->__toString() . "\n";
}
```

### Custom Configuration

```php
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create custom logger
$logger = new Logger('vat-service');
$logger->pushHandler(new StreamHandler('vat-service.log', Logger::INFO));

// Configure client
$config = ClientConfiguration::production($logger)
    ->withTimeout(30)
    ->withDebug(true);

$client = VatRetrievalClientFactory::create($config);
```

## Error Handling

The SDK provides comprehensive exception handling:

```php
use Netresearch\EuVatSdk\Exception\{
    InvalidRequestException,
    ServiceUnavailableException,
    ConfigurationException,
    VatServiceException
};

try {
    $response = $client->retrieveVatRates($request);
} catch (InvalidRequestException $e) {
    // Client-side validation errors, or a SOAP fault the service blames on the
    // request (faultcode env:Client / Sender).
    echo "Invalid request: " . $e->getMessage();
    // getErrorCode() returns the TEDB identifier from the faultstring, e.g.
    // "TEDB-ERR-2", or null when the error was raised locally by the SDK.
    echo "Error code: " . ($e->getErrorCode() ?? 'n/a');
} catch (ServiceUnavailableException $e) {
    // Network/transport failure, or a SOAP fault the service blames on itself
    // (faultcode env:Server / Receiver). getErrorCode() is null for transport errors.
    echo "Service unavailable: " . $e->getMessage();
} catch (ConfigurationException $e) {
    // Invalid SDK configuration
    echo "Configuration error: " . $e->getMessage();
} catch (VatServiceException $e) {
    // Any other SDK-related error
    echo "VAT service error: " . $e->getMessage();
}
```

## Testing

The SDK includes comprehensive test suites:

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run integration tests (requires network)
composer test:integration

# Run static analysis
composer analyse

# Run code style checks
composer cs:check

# Fix code style issues
composer cs:fix
```

### Test Environment

Integration tests use [php-vcr](https://github.com/php-vcr/php-vcr) to record and replay real SOAP interactions:

```bash
# Refresh recorded cassettes with live service calls
REFRESH_CASSETTES=true composer test:integration

# Enable debug output for tests
DEBUG_TESTS=true composer test
```

## Examples

See the `examples/` directory for comprehensive usage examples:

- [`basic-usage.php`](examples/basic-usage.php) - Simple VAT rate retrieval
- [`advanced-configuration.php`](examples/advanced-configuration.php) - Custom logging and configuration
- [`enterprise-integration.php`](examples/enterprise-integration.php) - Telemetry and monitoring
- [`batch-processing.php`](examples/batch-processing.php) - Multiple country queries
- [`error-handling.php`](examples/error-handling.php) - Exception handling patterns

## Framework Integration

### Symfony

```php
# config/services.yaml
services:
    # Configure the ClientConfiguration service first, injecting the logger here
    Netresearch\EuVatSdk\Client\ClientConfiguration:
        factory: ['Netresearch\EuVatSdk\Client\ClientConfiguration', 'production']
        arguments:
            - '@?logger' # Pass the logger, if it exists

    # The client service now only needs the pre-configured configuration service
    Netresearch\EuVatSdk\Client\VatRetrievalClientInterface:
        factory: ['Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory', 'create']
        arguments:
            - '@Netresearch\EuVatSdk\Client\ClientConfiguration'
```

### Laravel

```php
// config/app.php
'providers' => [
    // ...
    App\Providers\VatServiceProvider::class,
];

// app/Providers/VatServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Psr\Log\LoggerInterface;

class VatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VatRetrievalClientInterface::class, function ($app) {
            $logger = $app->make(LoggerInterface::class);
            
            return VatRetrievalClientFactory::create(
                ClientConfiguration::production($logger)
            );
        });
    }
}
```

## Performance

The SDK is optimized for production use:

- **WSDL Caching**: Automatic WSDL caching reduces initialization overhead
- **Connection Reuse**: Efficient SOAP connection handling
- **Memory Efficient**: Optimized DTOs and response handling for efficient batch processing
- **Benchmarks**: ~10ms typical response time for single country requests

### Recommended Production Settings

```php
$config = ClientConfiguration::production($logger)
    ->withTimeout(30)                    // 30 second timeout
    ->withSoapOptions([                  // Optimize SOAP client
        'cache_wsdl' => WSDL_CACHE_DISK,
        'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        'connection_timeout' => 30,
    ]);
```

## Security

### Input Validation

All inputs are strictly validated:

- Country codes must be valid 2-character ISO codes
- Dates are validated and normalized
- SOAP responses are schema-validated

### Error Information Disclosure

Error messages are carefully crafted to be helpful for debugging while avoiding sensitive information disclosure.

### Dependencies

All dependencies are regularly scanned for security vulnerabilities:

```bash
composer audit
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes with tests
4. Run the test suite (`composer test`)
5. Run static analysis (`composer analyse`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Development Requirements

- PHP 8.2+
- Composer 2.0+
- All quality tools must pass (PHPStan level 8, PHPCS PSR-12)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: Full API documentation available in the `docs/` directory
- **Issues**: Report bugs and feature requests on [GitHub Issues](https://github.com/netresearch/sdk-eu-vat/issues)
- **Security**: Report security vulnerabilities via [GitHub Security Advisories](https://github.com/netresearch/sdk-eu-vat/security/advisories/new)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed list of changes and upgrade instructions.

---

**Note**: This SDK provides access to official EU VAT data. Please ensure compliance with your local tax regulations and consult with tax professionals for specific tax advice.