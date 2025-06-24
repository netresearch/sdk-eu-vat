# EU VAT SOAP SDK - Implementation Plan (Refined)

## Project Overview
Building a framework-agnostic PHP SDK for the European Union's VAT Retrieval Service SOAP API. The SDK will provide a clean abstraction layer with DTOs, interfaces, and comprehensive error handling.

## Architecture Summary
- **Package**: `netresearch/sdk-eu-vat`
- **Namespace**: `Netresearch\EuVatSdk`
- **PHP Version**: 8.1+
- **Framework**: Agnostic
- **Distribution**: Composer package

## Key Architectural Decisions (Post-Gemini Review)
1. **Hybrid WSDL Scaffolding**: Use one-time generation for accuracy, then manual refinement
2. **Critical Date Fix**: Separate TypeConverters for xsd:date vs xsd:dateTime
3. **TDD Integration**: Build skeleton → failing test → implement until passing
4. **Enhanced Extensibility**: ClientConfiguration with event/middleware injection
5. **Production Observability**: Optional TelemetryInterface for enterprise monitoring
6. **php-vcr Testing**: Record/replay for reliable CI/CD integration tests

## Phase 1: Critical Foundation (Steps 1-4)

### Step 1: Project Structure & Composer Setup
**Goal**: Initialize the basic project structure with Composer configuration
**Deliverables**:
- `composer.json` with all dependencies
- Basic directory structure (`src/`, `tests/`, `resources/`)
- PSR-4 autoloading configuration
- Development dependencies (PHPUnit, PHPStan, etc.)

### Step 2: Exception Hierarchy
**Goal**: Implement the complete exception hierarchy for domain-specific error handling
**Deliverables**:
- Base `VatServiceException`
- All specific exception classes (SoapFaultException, InvalidRequestException, etc.)
- Proper inheritance structure
- PHPDoc documentation

### Step 3: Core Interfaces & Configuration
**Goal**: Define the main client interface and configuration class
**Deliverables**:
- `VatRetrievalClientInterface`
- `ClientConfiguration` class with factory methods
- Type hints and validation

## Phase 2: Data Transfer Objects (Steps 4-6)

### Step 4: Request DTOs
**Goal**: Implement request data structures with validation
**Deliverables**:
- `VatRatesRequest` class
- Input validation (country codes, date formats)
- Constructor with proper type declarations

### Step 5: Response DTOs - Base Classes
**Goal**: Create the foundation response classes
**Deliverables**:
- `VatRate` class with BigDecimal integration
- Helper methods (isStandard(), isReduced(), etc.)
- Proper value conversion methods

### Step 6: Response DTOs - Collection Classes
**Goal**: Implement response collection and result classes
**Deliverables**:
- `VatRateResult` class
- `VatRatesResponse` class with filtering methods
- Array type declarations and iteration support

## Phase 3: Type Conversion & SOAP Infrastructure (Steps 7-9)

### Step 7: Type Converters
**Goal**: Implement automatic type conversion between SOAP XML and PHP objects
**Deliverables**:
- `DateTimeTypeConverter` for date handling
- `BigDecimalTypeConverter` for precise decimal calculations
- Integration with php-soap/ext-soap-engine

### Step 8: Event System Foundation
**Goal**: Set up the event-driven architecture for extensibility
**Deliverables**:
- `FaultEventListener` for SOAP fault handling
- `RequestEventListener` and `ResponseEventListener` for debugging
- Exception mapping from SOAP faults to domain exceptions

### Step 9: Middleware System
**Goal**: Implement middleware support for cross-cutting concerns
**Deliverables**:
- `LoggingMiddleware` class
- Middleware interface implementation
- Integration points with the SOAP engine

## Phase 4: Core SOAP Client (Steps 10-12)

### Step 10: SOAP Client - Basic Implementation
**Goal**: Create the core SOAP client with basic functionality
**Deliverables**:
- `SoapVatRetrievalClient` class structure
- Engine initialization with ClassMap configuration
- Basic error handling structure

### Step 11: SOAP Client - Request/Response Handling
**Goal**: Implement the main API method with full error handling
**Deliverables**:
- `retrieveVatRates()` method implementation
- Complete exception mapping
- Request/response transformation

### Step 12: SOAP Client - Configuration Integration
**Goal**: Integrate all configuration options and event systems
**Deliverables**:
- Full configuration support (endpoints, timeouts, debug mode)
- Event dispatcher integration
- WSDL handling (local vs remote)

## Phase 5: Factory & Resource Management (Steps 13-14)

### Step 13: Factory Pattern Implementation
**Goal**: Provide convenient factory methods for client creation
**Deliverables**:
- `VatRetrievalClientFactory` class
- Production and test environment presets
- Simple creation methods

### Step 14: WSDL Resource Integration
**Goal**: Bundle and manage the WSDL file
**Deliverables**:
- Local WSDL file in `resources/`
- Fallback mechanisms
- Path resolution logic

## Phase 6: Testing & Quality Assurance (Steps 15-17)

### Step 15: Unit Test Foundation
**Goal**: Set up comprehensive unit testing
**Deliverables**:
- PHPUnit configuration
- Mock SOAP responses
- DTO validation tests
- Exception handling tests

### Step 16: Integration Tests
**Goal**: Test against real or simulated SOAP service
**Deliverables**:
- Integration test suite
- Test against EU acceptance environment
- Performance and timeout testing

### Step 17: Static Analysis & Code Quality
**Goal**: Ensure code quality and type safety
**Deliverables**:
- PHPStan level 8+ configuration
- PHP_CodeSniffer PSR-12 compliance
- Rector rules for PHP 8.1+ best practices

## Phase 7: Documentation & Examples (Steps 18-19)

### Step 18: Usage Examples
**Goal**: Provide comprehensive usage documentation
**Deliverables**:
- Basic usage examples
- Advanced configuration examples
- Error handling patterns
- Real-world scenarios

### Step 19: Final Integration & Validation
**Goal**: Ensure everything works together seamlessly
**Deliverables**:
- End-to-end testing
- Performance validation
- Documentation review
- Package validation

## Implementation Guidelines

### Safety Principles
1. **Incremental Development**: Each step builds on the previous one
2. **No Orphaned Code**: Everything integrates into the main flow
3. **Comprehensive Testing**: Each component is tested before moving forward
4. **Gemini Review**: Every step reviewed for quality and best practices

### Quality Standards
- PSR-4 autoloading
- PSR-12 coding standards
- PSR-3 logging interface
- Type safety with PHPStan level 8+
- Comprehensive error handling
- BigDecimal for financial calculations

### Dependencies Management
- Minimal required dependencies
- Clear separation between required and dev dependencies
- Version constraints that ensure compatibility
- Optional dependencies clearly marked

## Risk Mitigation
- SOAP service availability handled gracefully
- Network timeouts and retries
- Invalid data validation at input level
- Comprehensive exception hierarchy
- Backward compatibility considerations