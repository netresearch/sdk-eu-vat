# EU VAT SOAP SDK - Implementation Prompts

## Step 1: Project Structure & Composer Setup

```text
Create the foundational structure for a PHP 8.1+ SOAP SDK for EU VAT services. Set up the project with:

1. **Create composer.json** with these exact specifications:
   - Package: "netresearch/sdk-eu-vat"
   - Description: "PHP SDK for EU VAT Retrieval Service SOAP API"
   - PHP requirement: "^8.1"
   - Required dependencies:
     - "ext-soap": "*"
     - "ext-libxml": "*"
     - "php-soap/ext-soap-engine": "^2.0"
     - "psr/log": "^3.0"
     - "brick/math": "^0.12"
     - "symfony/event-dispatcher": "^6.0"
   - Dev dependencies:
     - "phpunit/phpunit": "^10.0"
     - "phpstan/phpstan": "^1.10"
     - "rector/rector": "^1.0"
     - "squizlabs/php_codesniffer": "^3.7"
     - "monolog/monolog": "^3.0"
     - "php-vcr/php-vcr": "^1.5"
     - "php-soap/wsdl-to-php": "^1.0"

2. **Create directory structure**:
   ```
   src/
   ├── Client/
   ├── DTO/
   │   ├── Request/
   │   └── Response/
   ├── Exception/
   ├── EventListener/
   ├── TypeConverter/
   ├── Middleware/
   ├── Factory/
   └── Telemetry/
   tests/
   ├── Unit/
   ├── Integration/
   └── fixtures/
   resources/
   ```

3. **Configure PSR-4 autoloading** for both src and tests
4. **Create basic .gitignore** for PHP projects
5. **Create phpunit.xml** with separate test suites for unit and integration tests
6. **Create basic README.md** stub

Requirements:
- Follow PSR-4 autoloading standards
- Use proper semantic versioning constraints
- Include all necessary PHP extensions
- Set up proper development workflow tools
```

## Step 2: Exception Hierarchy

```text
Implement the complete exception hierarchy for domain-specific error handling in a financial SOAP SDK.

Create these exception classes in src/Exception/:

1. **VatServiceException** (abstract base class)
   - Extends \Exception
   - Add PHPDoc explaining this is the base for all SDK errors

2. **SoapFaultException** extends VatServiceException
   - Constructor: (string $message, string $faultCode, string $faultString, ?\Throwable $previous = null)
   - Add getter methods for faultCode and faultString
   - This handles generic SOAP faults

3. **InvalidRequestException** extends VatServiceException
   - For client-side validation errors (TEDB-100, TEDB-101, TEDB-102)

4. **ServiceUnavailableException** extends VatServiceException
   - For network/service availability issues and TEDB-400 server errors

5. **ConfigurationException** extends VatServiceException
   - For invalid configuration (malformed WSDL path, invalid endpoints)

6. **ParseException** extends VatServiceException
   - For SOAP response parsing errors and BigDecimal conversion failures

7. **ValidationException** extends VatServiceException
   - For DTO validation failures (invalid country codes, dates)

8. **UnexpectedResponseException** extends VatServiceException
   - For unexpected API responses that don't match expected schema

Requirements:
- Use PHP 8.1+ features (constructor property promotion where appropriate)
- Add comprehensive PHPDoc for each exception
- Include code examples in PHPDoc showing when each exception is thrown
- Use proper inheritance hierarchy
- Make all exception messages clear and actionable
```

## Step 3: WSDL Scaffolding and DTO Generation

```text
Use hybrid WSDL scaffolding to generate accurate DTOs, then manually refine them for PHP 8.1+ and financial precision.

**Phase A: Generate Scaffolding**
1. Download the WSDL from: https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl
2. Save it to resources/VatRetrievalService.wsdl
3. Use php-soap/wsdl-to-php to generate initial DTOs in a temporary directory
4. Examine the generated structure to understand the exact WSDL schema

**Phase B: Create Refined DTOs**
Based on the generated scaffolding, create these classes in src/DTO/:

**Request/VatRatesRequest.php:**
- Constructor with memberStates array and DateTimeInterface situationOn
- Validate and normalize member states (uppercase, unique, 2-char format)
- Use ctype_upper() for validation
- Throw ValidationException for invalid input
- Add date boundary validation (not too far in future)

**Response/VatRate.php:**
- Constructor with type, value (string), and optional category
- Use Brick\Math\BigDecimal for precise decimal handling
- Normalize type to uppercase in constructor
- Add helper methods: isStandard(), isReduced(), isSuperReduced(), isParkingRate(), isZeroRate(), isExempt()
- Provide getValue() (string), getDecimalValue() (BigDecimal), and deprecated getValueAsFloat()
- Add proper PHPDoc warnings about float precision

**Response/VatRateResult.php:**
- Constructor with memberState, VatRate, DateTimeInterface situationOn, optional comment
- Simple value object with getters

**Response/VatRatesResponse.php:**
- Constructor with array of VatRateResult objects
- Add filtering methods: getResultsForCountry(), getResultsByCategory()
- Support iteration and array access

Requirements:
- Use PHP 8.1+ constructor property promotion
- Add strict type declarations
- Implement proper validation with clear error messages
- Use BigDecimal for all financial calculations
- Add comprehensive PHPDoc with usage examples
- Ensure all DTOs are immutable value objects
```

## Step 4: Type Converters (CRITICAL - Date Format Fix)

```text
Create TypeConverter classes to handle automatic conversion between SOAP XML types and PHP objects. This step is CRITICAL - incorrect date formatting will cause immediate service failures.

Create these classes in src/TypeConverter/:

**DateTypeConverter.php** (for xsd:date):
- Implement TypeConverterInterface from php-soap/ext-soap-engine
- getTypeName(): return 'date'
- convertXmlToPhp(): Convert string to DateTimeImmutable
- convertPhpToXml(): Format DateTimeInterface as 'Y-m-d' (DATE ONLY, no time component)
- Add error handling for invalid date strings
- Throw ParseException for conversion failures

**DateTimeTypeConverter.php** (for xsd:dateTime):
- Implement TypeConverterInterface
- getTypeName(): return 'dateTime'
- convertXmlToPhp(): Convert string to DateTimeImmutable
- convertPhpToXml(): Format DateTimeInterface as 'Y-m-d\TH:i:s' (full datetime)
- Add timezone handling if needed

**BigDecimalTypeConverter.php**:
- Implement TypeConverterInterface
- getTypeName(): return 'decimal'
- convertXmlToPhp(): Convert string to BigDecimal, catch MathException
- convertPhpToXml(): Convert BigDecimal to string
- Add bounds checking for reasonable financial values
- Throw ParseException for invalid decimals

Requirements:
- CRITICAL: Use 'Y-m-d' format for xsd:date (not 'Y-m-d\TH:i:s')
- Handle timezone conversions properly
- Add comprehensive error handling with clear messages
- Test conversion both ways (XML→PHP and PHP→XML)
- Add PHPDoc examples showing expected input/output formats
- Ensure BigDecimal precision is maintained
```

## Step 5: Core Interfaces and Configuration

```text
Define the main client interface and enhanced configuration class with extensibility support.

Create these files:

**Client/VatRetrievalClientInterface.php:**
- Define retrieveVatRates(VatRatesRequest $request): VatRatesResponse
- Add PHPDoc explaining the interface purpose
- Document all possible exceptions that can be thrown
- Include usage examples in PHPDoc

**Telemetry/TelemetryInterface.php:**
- Define recordRequest(string $operation, float $duration, array $context = []): void
- Define recordError(string $operation, string $errorType, array $context = []): void
- Add comprehensive PHPDoc explaining the observability purpose
- Include examples of context data

**Telemetry/NullTelemetry.php:**
- Implement TelemetryInterface with empty methods
- Use as default when no telemetry is configured

**Client/ClientConfiguration.php:**
- Constructor with endpoint, soapOptions, timeout, debug, logger, wsdlPath
- Add arrays for eventSubscribers and middleware
- Implement withEventSubscriber() and withMiddleware() methods (immutable pattern)
- Add withTelemetry() method
- Create static factory methods: production(), test()
- Add getters for all properties
- Use PSR-3 NullLogger as default
- Set appropriate SOAP options (WSDL_CACHE_DISK, connection_timeout)

Requirements:
- Use immutable value object pattern for ClientConfiguration
- Support method chaining for configuration building
- Add comprehensive validation in constructor
- Include examples in PHPDoc showing configuration usage
- Use PHP 8.1+ features (constructor property promotion, readonly where appropriate)
- Default to production-ready settings (disk caching, reasonable timeouts)
```

## Step 6: Event System and Middleware

```text
Implement the event-driven architecture for extensibility and debugging, with SOAP fault mapping.

Create these classes in src/EventListener/:

**FaultEventListener.php:**
- Implement EventSubscriber interface from Symfony
- Handle SOAP fault events from php-soap/ext-soap-engine
- Map specific fault codes to domain exceptions:
  - 'TEDB-100': InvalidRequestException('Invalid date format provided')
  - 'TEDB-101': InvalidRequestException('Invalid country code provided')
  - 'TEDB-102': InvalidRequestException('Empty member states array provided')
  - 'TEDB-400': ServiceUnavailableException('Internal application error in EU VAT service')
  - Default: SoapFaultException with original fault details
- Log fault details if logger is available
- Add comprehensive error context

**RequestEventListener.php:**
- Implement EventSubscriber interface
- Log outgoing SOAP requests (operation, arguments) at DEBUG level
- Include request timing information
- Sanitize sensitive data if any

**ResponseEventListener.php:**
- Implement EventSubscriber interface
- Log SOAP responses (operation, response type) at DEBUG level
- Include response timing and size information

Create in src/Middleware/:

**LoggingMiddleware.php:**
- Implement MiddlewareInterface from php-soap/ext-soap-engine
- Add request timing and logging
- Log at INFO level for normal operations
- Support telemetry integration
- Add request/response size monitoring

Requirements:
- Use Symfony EventDispatcher patterns
- Add proper PSR-3 logging integration
- Include comprehensive debug information
- Ensure fault mapping is tested against real service faults
- Support both debug and production logging levels
- Add performance monitoring hooks
- Use dependency injection for logger
```

## Step 7: Core SOAP Client Implementation

```text
Create the main SOAP client that orchestrates all components with TDD validation.

**Client/SoapVatRetrievalClient.php:**

Implement the complete SOAP client with these features:

1. **Constructor and Initialization:**
   - Accept ClientConfiguration
   - Initialize SOAP engine with all type converters and event listeners
   - Set up telemetry (use NullTelemetry if none provided)
   - Configure ClassMap for DTO mapping

2. **Engine Setup (initializeEngine method):**
   - Create ClassMapCollection with mappings for all DTOs
   - Add TypeConverterCollection with Date, DateTime, and BigDecimal converters
   - Configure ExtSoapOptions with endpoint, timeouts, WSDL path
   - Set up EventDispatcher with all listeners
   - Register custom event subscribers from configuration
   - Create SimpleEngine with proper driver

3. **Main API Method (retrieveVatRates):**
   - Accept VatRatesRequest parameter
   - Add telemetry timing around SOAP call
   - Handle all exception types and map to domain exceptions
   - Log request/response if debug mode enabled
   - Return fully-hydrated VatRatesResponse

4. **Error Handling:**
   - Catch \SoapFault and convert to SoapFaultException
   - Catch TransportException and convert to ServiceUnavailableException
   - Catch WsdlException and convert to ConfigurationException
   - Record errors in telemetry with proper context
   - Re-throw as domain exceptions

**Integration Test Setup:**
Create tests/Integration/SoapClientTest.php with php-vcr:
- Test against EU acceptance endpoint
- Record real SOAP requests/responses
- Test error scenarios (invalid country codes, malformed dates)
- Validate exception mapping works correctly

Requirements:
- Use php-soap/ext-soap-engine for modern SOAP handling
- Integrate all previously created components
- Add comprehensive error handling and logging
- Use TDD approach: create failing test first, then implement
- Ensure telemetry captures all operations
- Test against real EU VAT service acceptance endpoint
- Document SOAP engine configuration clearly
```

## Step 8: Factory and Resource Management ✅ COMPLETED

```text
Create factory pattern for easy client creation and manage WSDL resources.

**Factory/VatRetrievalClientFactory.php:**
- Static create() method with optional ClientConfiguration and LoggerInterface
- Static createForTesting() method that uses test endpoint
- Static createWithTelemetry() method for observability
- Add convenience methods for common configurations
- Include comprehensive usage examples in PHPDoc

**Resource Management:**
- Ensure local WSDL file is properly bundled
- Add fallback logic: local WSDL → remote WSDL → cached WSDL
- Implement WSDL validation and integrity checking
- Add configuration option to prefer local vs remote WSDL

**Advanced Configuration Support:**
- Support for custom event listeners via factory
- Support for custom middleware registration
- Environment-based configuration (dev/test/prod)
- Connection pooling preparation (interface, not implementation)

Requirements:
- Provide simple, one-line client creation for basic use cases
- Support complex enterprise configurations
- Add proper error handling for WSDL loading failures
- Include performance considerations (caching, connection reuse)
- Document all factory methods with examples
- Ensure factory is compatible with dependency injection containers
```

## Step 9: Testing Infrastructure ✅ COMPLETED

```text
Set up comprehensive testing with php-vcr for reliable CI/CD.

**Unit Tests (tests/Unit/):**
- Test all DTOs with valid/invalid data
- Test type converters with edge cases
- Test exception hierarchy and mapping
- Test configuration validation
- Mock SOAP responses for client testing
- Test BigDecimal precision with financial calculations

**Integration Tests (tests/Integration/):**
- Use php-vcr for record/replay functionality
- Test against EU acceptance endpoint initially
- Create cassettes for common scenarios:
  - Successful VAT rate retrieval
  - Invalid country code errors
  - Invalid date format errors
  - Service unavailable scenarios
- Test timeout and network error handling
- Validate real SOAP fault mapping

**Test Configuration:**
- Configure phpunit.xml with proper test suites
- Set up php-vcr with cassette storage
- Add test groups: @unit, @integration, @network
- Configure CI to run unit tests always, integration tests conditionally
- Add performance benchmarks for common operations

**Test Data and Fixtures:**
- Create realistic test data for EU member states
- Include edge cases: Brexit transition dates, new member states
- Test with various VAT rate types and categories
- Include boundary testing for dates and decimal values

Requirements:
- Use php-vcr/php-vcr for reliable integration testing
- Test all error conditions and exception mappings
- Include performance and memory usage tests
- Add documentation for running tests and refreshing cassettes
- Ensure tests can run offline after initial cassette recording
- Test with multiple PHP versions (8.1, 8.2, 8.3)
```

## Step 10: Quality Assurance and Static Analysis

```text
Set up comprehensive code quality tools and static analysis.

**PHPStan Configuration (phpstan.neon):**
- Set level 8 (maximum strictness)
- Configure rules for financial calculations (BigDecimal usage)
- Add custom rules for exception handling patterns
- Exclude generated/vendor files appropriately
- Add baseline for any necessary suppressions

**PHP_CodeSniffer Configuration:**
- Enforce PSR-12 coding standards
- Add custom rules for PHPDoc requirements
- Ensure proper type declarations and return types
- Validate proper exception handling patterns

**Rector Configuration:**
- Add PHP 8.1+ modernization rules
- Include dead code elimination
- Add type declaration improvements
- Configure coding standard improvements

**Additional Quality Tools:**
- Set up PHPMD for complexity analysis
- Configure PHPCPD for copy-paste detection
- Add security scanning with sensiolabs/security-checker
- Include dependency vulnerability scanning

**CI/CD Integration:**
- Create GitHub Actions or GitLab CI configuration
- Run all quality tools on every commit
- Fail builds on quality issues
- Generate coverage reports
- Run integration tests against EU acceptance environment

Requirements:
- Achieve 100% type coverage with PHPStan level 8
- Maintain PSR-12 compliance
- Keep complexity metrics below thresholds
- Ensure comprehensive test coverage (>95%)
- Document all quality tool configurations
- Include quality badges in README
```

## Step 11: Documentation and Examples

```text
Create comprehensive documentation and usage examples for enterprise adoption.

**README.md:**
- Clear project description and use cases
- Installation instructions via Composer
- Quick start example with common scenarios
- Links to detailed documentation
- Badge for build status, coverage, version
- Contributing guidelines

**Usage Examples:**
Create examples/ directory with:
- **basic-usage.php**: Simple VAT rate retrieval
- **advanced-configuration.php**: Custom logging, timeouts, debugging
- **enterprise-integration.php**: Telemetry, monitoring, error handling
- **batch-processing.php**: Multiple country queries
- **error-handling.php**: Comprehensive exception handling patterns

**API Documentation:**
- Generate API docs with phpDocumentor
- Include comprehensive examples in all PHPDoc blocks
- Document all exception scenarios
- Add architectural decision records (ADR)
- Include performance guidelines

**Integration Guides:**
- Framework integration examples (Symfony, Laravel, etc.)
- Dependency injection container configuration
- Monitoring and observability setup
- Production deployment guidelines
- Troubleshooting common issues

Requirements:
- Include working, executable examples
- Document all configuration options
- Provide clear error resolution guidance
- Add performance benchmarks and recommendations
- Include security considerations
- Document upgrade paths for future versions
```

## Step 12: Final Integration and Package Validation

```text
Perform end-to-end validation and prepare for distribution.

**Final Integration Testing:**
- Test complete workflow from installation to API calls
- Validate against all supported PHP versions (8.1+)
- Test in containerized environments (Docker)
- Verify memory usage and performance characteristics
- Test with various SOAP client configurations

**Package Validation:**
- Validate composer.json dependencies and constraints
- Test installation in fresh environments
- Verify autoloading works correctly
- Check for namespace conflicts
- Validate PSR compliance

**Performance Validation:**
- Benchmark common operations
- Memory usage profiling
- Connection handling efficiency
- Large response processing
- Concurrent request handling

**Security Review:**
- Input validation completeness
- Error message information disclosure
- Dependency vulnerability scan
- SOAP injection protection
- Logging data sanitization

**Distribution Preparation:**
- Tag stable version (1.0.0)
- Create release notes
- Update CHANGELOG.md
- Prepare packagist.org submission
- Set up automated releases

Requirements:
- Ensure all tests pass on supported PHP versions
- Validate package installs cleanly via Composer
- Confirm all features work in production-like environments
- Complete security and performance validation
- Prepare comprehensive release documentation
- Set up monitoring for package adoption and issues
```

---

## Implementation Guidelines

### Safety Principles for Each Step:
1. **Start with failing tests** - Write tests first that demonstrate the expected behavior
2. **Implement minimal viable feature** - Get basic functionality working before adding complexity
3. **Review with Gemini** - Have Gemini review each implementation before moving to next step
4. **Integrate immediately** - Ensure each component integrates with existing code
5. **Validate against real service** - Test critical components against EU acceptance endpoint

### Quality Gates:
- All static analysis tools pass (PHPStan level 8, PHP_CodeSniffer)
- Unit test coverage >95%
- Integration tests pass with recorded cassettes
- Memory usage within reasonable bounds
- Documentation is complete and accurate

### Risk Mitigation:
- **Date format critical bug** addressed in Step 4
- **WSDL accuracy** ensured by scaffolding in Step 3
- **Real service validation** via integration tests in Step 7
- **Production readiness** via telemetry and error handling throughout