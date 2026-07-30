# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-07-30

This release corrects the SOAP request encoding, the response conversion and the
exception contract. Each of those changes alters observable behaviour for existing
code, which is why it is a major version. Read "Breaking changes" before upgrading.

### Breaking changes

- **VAT rate values can be `null`.** `VatRate::getValue()`, `getDecimalValue()`,
  `getRawValue()` and `getValueAsFloat()` are nullable, and `__toString()` returns
  `''` for such a rate. The service schema (`VatRetrievalServiceType.xsd`) declares
  `value` as optional for *every* rate type, not only exempt ones — a rate a member
  state does not levy (for example `PARKING_RATE`) arrives without a value. Previously
  any value-less rate aborted the entire response with a `ConversionException`.
  Guard with `if ($rate->getValue() !== null)` before arithmetic. Note that
  `(string) $rate` yields `''`, which is **not** safe to pass to `BigDecimal::of()`.
- **Different exception types leave `retrieveVatRates()`.** SOAP faults the service
  attributes to the caller now surface as `InvalidRequestException`, and faults it
  attributes to itself as `ServiceUnavailableException`, where both previously
  surfaced as `SoapFaultException`. Every SDK exception raised while converting a
  response — including `ConversionException`, `ParseException`, `ValidationException`
  and `UnexpectedResponseException` — now propagates unchanged rather than being
  wrapped in `UnexpectedResponseException`, because the client catches the
  `VatServiceException` base class. Existing `catch` blocks may no longer match.
- **Failures that never reached the service are reported as such.** ext-soap raises
  local errors (a proxy or error page returning non-XML, a truncated response, a
  serialization error) with a bare `Client` fault code, which is indistinguishable
  from a service rejection by code alone. These now raise `ServiceUnavailableException`,
  which callers may retry, instead of `InvalidRequestException` telling them their
  input was wrong. A fault only counts as a service rejection when it carries a
  namespace-prefixed code or a TEDB identifier.
- **`FaultEventListener::extractErrorDetails()` was removed.** It had no caller inside
  the SDK; fault details are collected while the exception is built.
- **The event and middleware layers are removed**, about a quarter of the source. No
  default code path ever reached them: the only places the SDK constructed a listener
  or a middleware were the `@example` blocks in their own docblocks. Gone are
  `Netresearch\EuVatSdk\Engine\*` (`EventAwareEngine`, `MiddlewareEngine` and the three
  event classes), `Netresearch\EuVatSdk\Middleware\*` (`MiddlewareInterface`,
  `LoggingMiddleware`), `RequestEventListener`, `ResponseEventListener` and
  `CorrelationIdProvider` — the last generated an identifier that was never attached to
  a request nor exposed on a response, so it could not correlate anything.
  `FaultEventListener` stays and is now called directly by the client.
  With them go `ClientConfiguration::withMiddleware()`, `withEventSubscriber()` and
  `VatRetrievalClientFactory::createWithEventSubscribers()`, and the
  `ClientConfiguration` constructor no longer takes `$eventSubscribers` or `$middleware`.
  To observe requests, implement `TelemetryInterface`; to intercept them, decorate
  `VatRetrievalClientInterface`.
- **Telemetry is recorded for real.** `ClientConfiguration::withTelemetry()` and
  `VatRetrievalClientFactory::createWithTelemetry()` accepted an implementation and then
  never called it. The client now calls `recordRequest()` with the measured duration on
  success and `recordError()` on failure, so an implementation that was silently idle
  will start receiving calls. An exception thrown by a telemetry implementation is
  logged and swallowed: metrics cannot fail a VAT lookup.
- **Rate categories are populated, and they live on the result.**
  `VatRatesResponse::getResultsByCategory()` could only ever return an empty array: the
  converter hardcoded the category to `null`. It is now read from the response.
  `VatRetrievalServiceType.xsd` places `category` on `vatRateResults` as a sibling of
  `rate` rather than inside it, so it is exposed as `VatRateResult::getCategory()` (the
  identifier, for example `FOODSTUFFS`) and `VatRateResult::getCategoryDescription()`
  (the text the service sends with it). Both are `null` when the service reported no
  category, which the schema permits and which standard rates never carry.
  `VatRate::getCategory()` and the third `VatRate` constructor argument are **removed**:
  they modelled the data on the wrong object and could only return `null`. Replace
  `$result->getRate()->getCategory()` with `$result->getCategory()`. The identifiers are
  defined in the TEDB External Interface Specification, not in the schema, so an
  unrecognised identifier passed to `getResultsByCategory()` returns `[]` rather than
  raising an error.
- **`symfony/event-dispatcher` and `ramsey/uuid` are no longer required.** Nothing in
  the SDK used them once the event layer and `CorrelationIdProvider` were gone. Projects
  relying on them transitively through this package must require them directly.
- **`getResults()` returns one entry per rate type and member state.** The service
  answers a single-country query with every rate it publishes for that country, so a
  request for one member state commonly yields dozens of results. Code assuming one
  result per country — `$results[0]`, or an array keyed by member state — silently
  used an arbitrary rate. Select on `VatRate::isStandard()` or `getType()`, or use
  `VatRatesResponse::getResultsForCountry()`. This behaviour is unchanged; it was
  always the case and is documented here because the SDK's own examples got it wrong.
- **The documented TEDB fault codes were fictional.** `TEDB-100`, `TEDB-101`,
  `TEDB-102` and `TEDB-400` appear in no service response, WSDL or schema; they
  existed only in this SDK's own documentation and were never produced. The service
  sends a responsibility marker in `faultcode` plus a code such as `TEDB-ERR-2` in
  `faultstring`. Code branching on the old constants never matched and must be
  rewritten against `getErrorCode()`, which now returns the real code, or `null`
  when the fault string carries none.
- **Rate value accessors preserve the scale sent by the service.** `getRawValue()`
  and `(string) $rate->getValue()` return `"17.0"` where they previously returned
  `"17"`. `BigDecimalTypeConverter` registered for `xsd:decimal`, a type no schema in
  `resources/` uses, so its typemap entry never matched: ext-soap decoded the
  `xs:double` rate value into a PHP float and the trailing zero was lost. It now
  registers for `xsd:double` and parses the element text verbatim. String comparisons
  and array keys built from these accessors change; numeric comparisons do not.
- **`BigDecimalTypeConverter` no longer range-checks values.** Values outside the
  former -10..100 window are returned instead of raising `ParseException`. The check
  ran on a `toFloat()` round-trip and would have aborted an entire multi-member-state
  response over one implausible rate; plausibility is the caller's decision.
- **`BigDecimalTypeConverter::convertPhpToXml()` returns a complete XML element**
  (`<double>19.75</double>`) instead of a bare numeric string, and
  `convertXmlToPhp()` accepts the complete element ext-soap passes and returns `null`
  for an empty one. This class is public; code reusing it in a custom typemap or
  asserting on its return value is affected.
- **`DateTypeConverter::convertPhpToXml()` returns a complete XML element**
  (`<date>2024-01-15</date>`) instead of a bare date string, as the ext-soap typemap
  contract requires. This class is public; code reusing it in a custom typemap or
  asserting on its return value is affected.

### Added

- PHP 8.4 to the CI test and static-analysis matrices
- The `network` test group runs in CI, replaying committed cassettes offline; it was
  previously excluded everywhere, which is how the broken fault mapping stayed hidden
- `examples/` is covered by static analysis, which catches an example calling a method
  that does not exist. It cannot catch everything: a value the example itself types as
  `mixed` hides an argument mismatch from the analyser

### Changed

- Least-privilege permissions declared in the security workflow
- Migrated the quality-assurance workflow to the shared netresearch/.github php-ci
  reusable workflow
- Replaced generic contact emails with GitHub references
- Integration tests are enforced in CI instead of being advisory
- CI installs dependencies fresh from composer.json (composer.lock is no longer committed)
- `php-vcr/php-vcr` constrained to `>=1.6.4 <1.8.2`: from 1.8.2 its `SoapClient`
  declares a parameter the ext-soap-engine releases installable on PHP 8.2 and 8.3
  do not, which is a fatal error rather than a test failure

### Fixed

- `situationOn` was transmitted as an empty element, so the service answered
  historical queries with current rates. All cassettes were re-recorded against the
  corrected request body
- Rates without a value no longer abort conversion of the whole response
- SOAP faults are classified against the shapes the service actually returns
- Documentation and examples called `getVatRate()`, which does not exist, and
  dereferenced nullable rate values unguarded
- Examples keyed rates by member state, silently keeping one arbitrary rate per
  country although the service returns several
- Removed deprecated `libxml_disable_entity_loader()` calls and restored libxml error
  handling in a `finally` block
- Host-identifying data (developer IP addresses, local certificate paths) removed from
  the recorded cassettes
- Cassettes are tracked by an explicit `.gitignore` rule instead of manual force-adds,
  so a fresh clone replays offline rather than recording live traffic
- php-vcr is started from the PHPUnit bootstrap. It patches the SOAP transport while
  that class is being included, so a suite that touched the transport first left it
  unpatched and every later replay in that process reached the live service instead
- The performance benchmarks are removed along with their CI job. The job executed
  nothing at all — every test in it was excluded by group, so it reported success
  without running one — and the suite behind it called the live service. What was
  worth keeping is now two guards in the integration suite: a request for every
  member state must cost a single service call, and a request no cassette answers
  must fail locally instead of reaching the service
- `failOnEmptyTestSuite` is set, so no CI gate can report success while executing no
  tests

### Removed

- Internal planning documents (`.github/internal/`) from the repository
- `composer.lock` (consumers of a library resolve their own dependency set)

## [1.1.0] - 2025-08-12

### Changed
- Widened brick/math dependency constraint to support versions 0.11 and 0.12 for better Oro Commerce compatibility
- Widened psr/log dependency constraint to support versions 1.x, 2.x, and 3.x for broader framework compatibility
- Updated monolog dev dependency to support versions 2.10+ and 3.x

### Fixed
- Fixed PSR-3 LoggerInterface compatibility issue in security review tests
- Refreshed all VCR cassettes for reliable integration testing

## [1.0.1] - 2025-07-07

### Fixed
- VatRate mapping for unmapped rate types to handle edge cases properly

### Changed
- Updated GitHub Actions cache action to v4 for improved CI performance

### Added
- Renovate configuration for automated dependency updates

## [1.0.0] - 2025-06-24

### Added
- Initial release of EU VAT SOAP SDK
- Complete implementation of EU VAT Retrieval Service integration
- PHP 8.2+ support with modern language features
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
- Rector for PHP 8.2+ modernization
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