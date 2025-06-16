# Release Notes - EU VAT SOAP SDK v1.0.0

## Overview

We are excited to announce the first stable release of the EU VAT SOAP SDK! This PHP 8.1+ SDK provides reliable access to the European Union's official VAT Retrieval Service with enterprise-grade features.

## Key Features

### 🏦 Financial-Grade Precision
- Uses `brick/math` BigDecimal for exact VAT calculations
- No floating-point precision errors
- Safe for financial applications

### 🛡️ Enterprise Ready
- Comprehensive error handling with typed exceptions
- Built-in request/response logging
- Telemetry support for monitoring
- Circuit breaker pattern example

### 🧪 Thoroughly Tested
- 398+ tests with 95%+ code coverage
- Integration tests against real EU service
- PHPStan level 8 static analysis
- PSR-12 compliant code

### 🔄 Modern Architecture
- Built on `php-soap/ext-soap-engine`
- Event-driven with middleware support
- Immutable DTOs
- Full PHP 8.1+ feature usage

## Installation

```bash
composer require netresearch/sdk-eu-vat
```

## Quick Start

```php
use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;

$client = VatRetrievalClientFactory::create();

$request = new VatRatesRequest(
    memberStates: ['DE', 'FR', 'IT'],
    situationOn: new DateTime('2024-01-01')
);

$response = $client->retrieveVatRates($request);

foreach ($response->getResults() as $result) {
    echo sprintf(
        "%s: %s%%\n",
        $result->getMemberState(),
        $result->getVatRate()->getValue()
    );
}
```

## Performance Characteristics

- Client initialization: ~5ms
- Single country request: ~10ms
- All EU countries (27): ~50ms
- Memory usage: < 5MB for full EU query
- BigDecimal calculations: ~1μs per operation

## Security Features

- Input validation with whitelist approach
- SOAP injection protection
- No sensitive data in error messages
- HTTPS-only endpoints
- Secure default configuration

## Framework Support

The SDK is framework-agnostic but includes integration examples for:
- Symfony
- Laravel
- Generic PSR-11 containers

## Documentation

- Comprehensive README with examples
- 5 detailed example scripts
- Full API documentation
- Migration guides
- Performance guidelines

## Requirements

- PHP 8.1 or higher
- ext-soap
- ext-libxml

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on contributing to the project.

## Support

- Documentation: See the `docs/` directory
- Issues: [GitHub Issues](https://github.com/netresearch/sdk-eu-vat/issues)
- Security: security@netresearch.de

## License

This SDK is released under the MIT License.

## Acknowledgments

- European Commission for providing the VAT Retrieval Service
- PHP community for excellent libraries
- All contributors and testers

---

**Note**: This SDK is not affiliated with or endorsed by the European Commission.