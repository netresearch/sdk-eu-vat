# Installation Guide

## Requirements

- PHP 8.1 or higher
- `ext-soap` PHP extension
- `ext-libxml` PHP extension

## 1. Install via Composer

```bash
composer require netresearch/sdk-eu-vat
```

## 2. Install Required PHP Extensions

This SDK requires the `soap` and `libxml` PHP extensions.

### Debian/Ubuntu

```bash
sudo apt-get update
sudo apt-get install php8.1-soap php8.1-xml

# For PHP 8.2 or 8.3, adjust the version number accordingly
sudo apt-get install php8.2-soap php8.2-xml
```

### CentOS/RHEL/Fedora

```bash
# RHEL/CentOS 8+
sudo dnf install php-soap php-xml

# Older versions
sudo yum install php-soap php-xml
```

### Alpine Linux

```bash
apk add php81-soap php81-xml

# For PHP 8.2 or 8.3, adjust the version number accordingly
apk add php82-soap php82-xml
```

### macOS (Homebrew)

```bash
brew install php@8.1

# Extensions are usually included, but verify they're enabled
php -m | grep -E '(soap|libxml)'
```

### Windows (XAMPP/WAMP)

1. Open your `php.ini` file
2. Uncomment the following lines by removing the semicolon:
   ```ini
   extension=soap
   extension=libxml
   ```
3. Restart your web server

## 3. Verify Installation

Check that the extensions are properly installed:

```bash
php -m | grep soap
php -m | grep libxml
```

Both commands should return the extension name if properly installed.

## 4. Quick Test

Create a simple test script to verify everything is working:

```php
<?php

require_once 'vendor/autoload.php';

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;

try {
    $client = VatRetrievalClientFactory::create();
    echo "✓ SDK successfully installed and configured\n";
    
    // Optional: Test with a simple request
    $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
    $response = $client->retrieveVatRates($request);
    echo "✓ Successfully connected to EU VAT service\n";
    
} catch (\Exception $e) {
    echo "✗ Installation error: " . $e->getMessage() . "\n";
}
```

## Troubleshooting

### "Class 'SoapClient' not found"

This means the `ext-soap` extension is not installed or not enabled. Follow the installation steps above for your operating system.

### "Call to undefined function libxml_use_internal_errors()"

This means the `ext-libxml` extension is not installed or not enabled. This extension is usually included with PHP, but may need to be explicitly installed on some systems.

### Permission Issues

If you encounter permission issues during installation:

```bash
# Use sudo with composer (not recommended)
sudo composer require netresearch/sdk-eu-vat

# Or better: fix composer permissions
sudo chown -R $USER:$USER ~/.composer
composer require netresearch/sdk-eu-vat
```

### Behind Corporate Firewall

If you're behind a corporate firewall, you may need to configure Composer to use your proxy:

```bash
composer config --global http-proxy http://proxy.company.com:8080
composer config --global https-proxy https://proxy.company.com:8080
```

## Next Steps

Once installation is complete, see the [README.md](README.md) for usage examples and configuration options.