# EU VAT SOAP SDK

A framework-agnostic PHP SDK for interacting with the European Union's official VAT Retrieval Service SOAP API.

## Features

- 🚀 PHP 8.1+ with strict typing and modern features
- 💰 Precise financial calculations using BigDecimal
- 🔧 Framework-agnostic design
- 📊 Optional telemetry and monitoring support
- 🛡️ Comprehensive error handling
- 🔌 Extensible via events and middleware
- ✅ Fully tested with unit and integration tests

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
    situationOn: new \DateTime('2024-01-01')
);

$response = $client->retrieveVatRates($request);

foreach ($response->getResults() as $result) {
    $rate = $result->getRate();
    echo sprintf(
        "Country: %s, Type: %s, Rate: %s%%\n",
        $result->getMemberState(),
        $rate->getType(),
        $rate->getValue()
    );
}
```

## Documentation

Full documentation is available at [docs/](docs/).

## Requirements

- PHP 8.1 or higher
- ext-soap
- ext-libxml

## Testing

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests
composer test:integration
```

## License

This SDK is released under the MIT License. See [LICENSE](LICENSE) for details.