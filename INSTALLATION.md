# Installation Troubleshooting

## Installing Required PHP Extensions

This SDK requires the `soap` and `libxml` PHP extensions.

### Debian/Ubuntu

```bash
sudo apt-get update
sudo apt-get install php8.2-soap php8.2-xml

# For PHP 8.3, adjust the version number accordingly
sudo apt-get install php8.3-soap php8.3-xml
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
apk add php82-soap php82-xml

# For PHP 8.3, adjust the version number accordingly
apk add php83-soap php83-xml
```

### macOS (Homebrew)

```bash
brew install php@8.2

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

## Verifying Extension Installation

Check that the extensions are properly installed:

```bash
php -m | grep soap
php -m | grep libxml
```

Both commands should return the extension name if properly installed.

## Common Installation Issues

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

## Still Having Issues?

If you continue to experience installation problems after following this guide, please:

1. Check that your PHP version is 8.2 or higher: `php -v`
2. Verify all required extensions are loaded: `php -m | grep -E '(soap|libxml)'`
3. Review the [README.md](README.md) for usage examples once installation is complete