# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- No unreleased changes yet

## [1.0.0] - 2025-06-24

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

## Additional Documentation

For detailed guides and best practices, see the `docs/` directory:
- [Migration Guide](docs/migration-guide.md) - Migrating from legacy VAT services
- [Security Guide](docs/security.md) - Security considerations and best practices  
- [Performance Guide](docs/performance.md) - Optimization tips and benchmarks

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