# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

Essential commands for development workflow:

```bash
# Development setup
composer install                    # Install dependencies

# Testing
composer test                      # Run all tests (unit + integration)
composer test:unit                 # Run only unit tests (fast)
composer test:integration          # Run integration tests (requires network or VCR cassettes)
composer test:coverage             # Generate coverage report

# Code Quality
composer quality                   # Run all quality checks (PHPStan, PHPCS, PHPMD, Rector)
composer analyse                   # PHPStan static analysis (level 8)
composer cs-check                  # Check code style (PSR-12)
composer cs-fix                    # Fix code style issues
composer rector                    # Check for code modernization opportunities
composer rector:fix                # Apply Rector fixes
composer phpmd                     # PHP Mess Detector analysis

# CI Pipeline
composer ci                       # Run CI checks (unit tests + quality)
```

**Important**: Always run `composer quality` before committing to ensure code meets standards.

## Architecture Overview

This is a modern PHP 8.1+ SOAP SDK for the EU VAT Retrieval Service with enterprise-grade features:

### Core Components

- **Client Layer** (`src/Client/`): SOAP client abstraction with `SoapVatRetrievalClient` and configuration management
- **DTOs** (`src/DTO/`): Request/Response objects for type-safe API interactions
- **Factory** (`src/Factory/`): `VatRetrievalClientFactory` for creating configured client instances
- **Exception Hierarchy** (`src/Exception/`): Domain-specific exceptions for robust error handling
- **Event System** (`src/EventListener/`): Request/Response/Fault event listeners for observability
- **Middleware** (`src/Middleware/`): Logging and telemetry middleware
- **Type Converters** (`src/TypeConverter/`): Precise financial calculations using `brick/math`

### Key Dependencies

- **php-soap/ext-soap-engine**: Modern SOAP client engine
- **brick/math**: Financial-grade decimal precision
- **symfony/event-dispatcher**: Event-driven architecture
- **psr/log**: Standard logging interface

### Design Patterns

- **Factory Pattern**: `VatRetrievalClientFactory` for client creation
- **Event-Driven Architecture**: Request/Response lifecycle hooks
- **Middleware Pattern**: Logging and telemetry injection
- **DTO Pattern**: Type-safe request/response handling
- **Exception Hierarchy**: Domain-specific error handling

## Testing Strategy

### Test Structure
- **Unit Tests** (`tests/Unit/`): Fast, isolated component tests
- **Integration Tests** (`tests/Integration/`): Real SOAP service interactions using php-vcr
- **VCR Cassettes** (`tests/fixtures/cassettes/`): Recorded SOAP interactions for deterministic tests

### VCR (Video Cassette Recorder) Usage
Integration tests use php-vcr to record real SOAP interactions and replay them:

```bash
# Refresh cassettes with live service calls
REFRESH_CASSETTES=true composer test:integration

# Enable test debugging
DEBUG_TESTS=true composer test
```

**Important**: When modifying SOAP requests/responses, delete relevant cassette files in `tests/fixtures/cassettes/` to re-record with live service.

### Test Environment Variables
- `USE_PRODUCTION_ENDPOINT`: Use production vs acceptance endpoint
- `REFRESH_CASSETTES`: Force re-recording of VCR cassettes
- `DEBUG_TESTS`: Enable verbose test output

## Code Quality Standards

### PHPStan Configuration
- **Level 8**: Maximum strictness for type safety
- **Financial Rules**: Custom rules for BigDecimal usage
- **Exception Handling**: Strict exception type checking
- **Bootstrap**: `tests/bootstrap.php` for test environment setup

### Code Style
- **Standard**: PSR-12 with Slevomat Coding Standard extensions
- **Tools**: PHP_CodeSniffer with automatic fixing via PHPCBF
- **Enforcement**: CI pipeline blocks merges on style violations

### Static Analysis Tools
- **PHPStan**: Type safety and bug detection (level 8)
- **PHPMD**: Code complexity and design issues
- **Rector**: Code modernization suggestions
- **PHPCS**: Code style enforcement

## Financial Precision Requirements

This SDK handles financial calculations requiring exact precision:

- **Use `brick/math` BigDecimal** for all VAT calculations
- **Never use float/double** for monetary values
- **Type converters** in `src/TypeConverter/` handle precise decimal conversion
- **PHPStan rules** enforce BigDecimal usage in financial contexts

## SOAP Integration Details

### WSDL Resources
- Location: `resources/VatRetrievalService.wsdl`
- Schema files: `resources/*.xsd`
- **ClassMap configuration**: Uses XML element names, not XSD type names

### Event Lifecycle
1. **RequestEventListener**: Log outgoing SOAP requests
2. **ResponseEventListener**: Parse and validate responses
3. **FaultEventListener**: Handle SOAP fault mapping

### Configuration
- **Production**: `ClientConfiguration::production()`
- **Acceptance**: `ClientConfiguration::acceptance()` 
- **Custom options**: Timeout, debug mode, SOAP client options

## Development Conventions

### Namespace Structure
- Root namespace: `Netresearch\EuVatSdk`
- Test namespace: `Netresearch\EuVatSdk\Tests`
- Follow PSR-4 autoloading standards

### Error Handling
- All SDK exceptions extend `VatServiceException`
- Use specific exception types for different failure modes
- Include contextual information in exception messages
- Never expose sensitive data in exception messages

### Logging
- Use PSR-3 compatible loggers
- Log levels: DEBUG for SOAP traffic, INFO for operations, ERROR for failures
- Include request IDs for traceability

## Performance Considerations

- **WSDL Caching**: Enable `WSDL_CACHE_DISK` in production
- **Connection Reuse**: SOAP client handles connection pooling
- **Memory Efficiency**: DTOs designed for batch processing
- **Benchmarks**: Target ~10ms response time for single country requests

## Security Notes

- **Input Validation**: All request parameters are strictly validated
- **Schema Validation**: SOAP responses validated against XSD schemas
- **Error Messages**: Careful to avoid information disclosure
- **Dependencies**: Regular security audits via `composer audit`