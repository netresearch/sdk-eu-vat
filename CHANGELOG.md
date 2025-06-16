# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- No unreleased changes yet

## [1.0.0] - 2024-XX-XX

### Added
- Initial release of EU VAT SOAP SDK
- Complete implementation of EU VAT Retrieval Service integration
- PHP 8.1+ support with modern language features
- Financial-grade precision using BigDecimal for VAT calculations
- Core VAT rate retrieval functionality
- Support for all EU member states
- Historical VAT rate queries
- Comprehensive exception hierarchy
- Event-driven architecture with middleware support
- Built-in telemetry and observability features
- Type converters for SOAP XML mapping
- Factory pattern for easy client creation
- Extensive test suite with 398+ tests and real service validation
- Static analysis tools (PHPStan level 8, PHPCS PSR-12)
- Comprehensive documentation and usage examples
- CI/CD pipeline with quality gates
- Framework-agnostic design with Symfony and Laravel integration examples
- PSR-3 logging support and PSR-4 autoloading
- Production-ready configuration

### DTOs (Data Transfer Objects)
- `VatRatesRequest` - Request DTO with validation
- `VatRatesResponse` - Response collection with filtering
- `VatRate` - Financial rate with BigDecimal precision
- `VatRateResult` - Individual country result

### Client Features
- `SoapVatRetrievalClient` - Main SOAP client implementation
- `ClientConfiguration` - Immutable configuration builder
- `VatRetrievalClientFactory` - Factory for easy instantiation
- Support for production and test endpoints
- Configurable timeouts and SOAP options

### Exception Handling
- `VatServiceException` - Base exception for all SDK errors
- `InvalidRequestException` - Client-side validation errors
- `ServiceUnavailableException` - Network and service issues
- `ConfigurationException` - SDK configuration errors
- `SoapFaultException` - SOAP fault mapping
- `ParseException` - Response parsing errors
- `ValidationException` - DTO validation failures
- `UnexpectedResponseException` - Schema validation errors

### Type Converters
- `DateTypeConverter` - xsd:date to PHP DateTime conversion
- `DateTimeTypeConverter` - xsd:dateTime handling
- `BigDecimalTypeConverter` - Precise decimal conversion

### Event System
- `FaultEventListener` - SOAP fault to exception mapping
- `RequestEventListener` - Request logging and timing
- `ResponseEventListener` - Response logging and metrics
- `LoggingMiddleware` - Comprehensive request/response logging

### Quality Assurance
- PHPStan level 8 static analysis
- PHP_CodeSniffer PSR-12 compliance
- Rector for PHP 8.1+ modernization
- PHPMD complexity analysis
- GitHub Actions CI/CD pipeline
- 95%+ test coverage

### Documentation
- Comprehensive README with examples
- API documentation with PHPDoc
- Usage examples for common scenarios
- Framework integration guides
- Performance and security guidelines
- Troubleshooting documentation

### Examples
- Basic usage patterns
- Advanced configuration
- Error handling strategies
- Batch processing techniques
- Enterprise integration patterns

## Migration Guides

### From Legacy VAT Services

If you're migrating from a legacy VAT service implementation:

1. **Replace direct SOAP calls** with the SDK client:
   ```php
   // Old approach
   $soapClient = new SoapClient($wsdlUrl);
   $result = $soapClient->retrieveVatRates($params);
   
   // New approach
   $client = VatRetrievalClientFactory::create();
   $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
   $response = $client->retrieveVatRates($request);
   ```

2. **Update error handling** to use typed exceptions:
   ```php
   // Old approach
   try {
       $result = $soapClient->call();
   } catch (SoapFault $e) {
       // Generic error handling
   }
   
   // New approach
   try {
       $response = $client->retrieveVatRates($request);
   } catch (InvalidRequestException $e) {
       // Handle validation errors
   } catch (ServiceUnavailableException $e) {
       // Handle service issues
   }
   ```

3. **Migrate to BigDecimal** for financial calculations:
   ```php
   // Old approach (floating point issues)
   $vatAmount = $netAmount * ($vatRate / 100);
   
   // New approach (precise)
   $net = BigDecimal::of($netAmount);
   $rate = BigDecimal::of($vatRate);
   $vatAmount = $net->multipliedBy($rate)->dividedBy('100', 2);
   ```

## Security Considerations

### Supported Security Features
- Input validation and sanitization
- SOAP injection protection
- Secure error message handling
- TLS/SSL for all communications
- No sensitive data in logs

### Security Updates
This section will be updated with any security-related changes or advisories.

## Performance Notes

### Benchmarks
- Typical response time: ~10ms for single country requests
- Memory usage: ~2MB for full EU member state queries
- WSDL caching reduces initialization by ~100ms

### Optimization Tips
- Use WSDL caching in production (`WSDL_CACHE_DISK`)
- Batch multiple countries in single requests
- Implement application-level caching for frequently accessed rates
- Use appropriate timeouts for your use case

## Breaking Changes

This section will document any breaking changes in future releases.

## Credits

### Core Contributors
- Netresearch DTT GmbH Development Team

### Dependencies
- `php-soap/ext-soap-engine` - Modern SOAP client engine
- `brick/math` - Arbitrary precision mathematics
- `symfony/event-dispatcher` - Event system
- `psr/log` - Logging abstraction

### Acknowledgments
- European Commission for providing the VAT Retrieval Service
- PHP community for excellent libraries and tools
- Contributors and testers who helped improve the SDK

---

**Note**: This SDK is not affiliated with or endorsed by the European Commission. It is an independent implementation for accessing the official EU VAT Retrieval Service.