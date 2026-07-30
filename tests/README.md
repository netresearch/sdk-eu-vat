# EU VAT SDK Test Suite

This directory contains the test suite for the EU VAT SOAP SDK: unit tests and
integration tests that replay recorded service responses.

## Overview

The test suite is organized into two categories, plus the recorded data they replay:

- **Unit Tests** (`tests/Unit/`): Fast, isolated tests for individual components
- **Integration Tests** (`tests/Integration/`): Tests against the actual EU VAT service using php-vcr
- **Fixtures** (`tests/fixtures/`): the VCR cassettes in `cassettes/`, plus `vcr-bootstrap.php`
  (the PHPUnit bootstrap) and `vcr-config.php`. There is no shared fixture or data-provider
  class: assertions are written against the recorded responses.

## Running Tests

### All Tests
```bash
./vendor/bin/phpunit
```

### Unit Tests Only
```bash
./vendor/bin/phpunit --testsuite=unit
```

### Integration Tests Only
```bash
./vendor/bin/phpunit --testsuite=integration
```

### Test Groups

`phpunit.xml` excludes the `network` and `slow` groups globally, so a plain run never
needs network access and `--exclude-group=network` is redundant.

Do not run `--group=slow`. Those tests are not cassette-backed: they call the live EU
service. The group is excluded in `phpunit.xml`, and with `failOnEmptyTestSuite="true"`
a selection that resolves to no tests is itself a failure.

## Integration Testing with php-vcr

Integration tests use [php-vcr](https://github.com/php-vcr/php-vcr) to record and replay HTTP/SOAP interactions. This allows tests to run reliably offline after initial recording.

### How VCR Works

1. **First Run**: VCR records actual SOAP requests/responses to cassette files
2. **Subsequent Runs**: VCR replays recorded responses without hitting the network
3. **Cassettes**: Stored in `tests/fixtures/cassettes/` as JSON files

### Recording New Cassettes

To record fresh cassettes (e.g., when the API changes):

```bash
# Set environment variable to force recording
REFRESH_CASSETTES=true ./vendor/bin/phpunit tests/Integration/

# Or for a specific test
REFRESH_CASSETTES=true ./vendor/bin/phpunit tests/Integration/VatRateRetrievalTest.php
```

### Environment Variables

Configure test behavior with these environment variables:

- `USE_PRODUCTION_ENDPOINT`: Set to `true` to point `IntegrationTestCase` at the production
  endpoint (default: `false`, i.e. the acceptance endpoint)
- `REFRESH_CASSETTES`: Set to `true` to force re-recording of VCR cassettes

Both are read by `tests/Integration/IntegrationTestCase.php` and defaulted in the `<php>`
block of `phpunit.xml`.

Example:
```bash
USE_PRODUCTION_ENDPOINT=true ./vendor/bin/phpunit --testsuite=integration
```

## Test Structure

### Unit Tests

Located in `tests/Unit/`, these tests cover:

- DTO validation and serialization
- Type converter edge cases
- Exception hierarchy
- Configuration validation
- Factory methods
- Individual component behavior

### Integration Tests

Located in `tests/Integration/`, these tests verify:

- Successful VAT rate retrieval for single/multiple countries
- Error handling (invalid country codes, dates, etc.)
- Historical data queries (Brexit transition, etc.)
- That a multi-country request costs one service call, and that an unrecorded
  request fails locally instead of reaching the service (ServiceInteractionTest)

### Test Data

Tests take their data from the recorded cassettes in `tests/fixtures/cassettes/`
rather than from a shared fixture class, so an assertion describes a response the
service really sent. Each test names the cassette it replays.

`tests/fixtures/vcr-bootstrap.php` is the PHPUnit bootstrap. It turns php-vcr on
before any test file is loaded, because php-vcr patches the SOAP transport while
that class is being included: if a test loads the transport first, the patch never
happens and a later replay would reach the live service instead.

## Writing New Tests

### Unit Test Example

```php
class MyComponentTest extends TestCase
{
    /**
     * @test
     */
    public function testComponentBehavior(): void
    {
        $component = new MyComponent();
        
        $result = $component->doSomething('input');
        
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example

```php
class MyIntegrationTest extends IntegrationTestCase
{
    /**
     * @test
     * @group integration
     */
    public function testRealServiceInteraction(): void
    {
        // Insert cassette for recording/replay
        $this->insertCassette('my-test-cassette');

        $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
        $response = $this->client->retrieveVatRates($request);

        // The service answers with one row per rate type (STANDARD, REDUCED,
        // PARKING_RATE, ...), so even a single-country request returns several
        // results. Never assert a row count as a proxy for the country count;
        // assert on the rate type the test is actually about.
        $standardRates = array_filter(
            $response->getResults(),
            static fn (VatRateResult $result): bool => $result->getRate()->isStandard()
        );

        $this->assertCount(1, $standardRates, 'DE has exactly one standard rate');
    }
}
```

## CI/CD Integration

For continuous integration:

```yaml
# Example GitHub Actions configuration
- name: Run Unit Tests
  run: ./vendor/bin/phpunit --testsuite=unit

- name: Run Integration Tests
  run: ./vendor/bin/phpunit --testsuite=integration --exclude-group=slow
  env:
    USE_PRODUCTION_ENDPOINT: false
```

## Troubleshooting

### Common Issues

1. **Cassette Not Found**
   - Ensure cassettes are committed to version control
   - Run with `REFRESH_CASSETTES=true` to record new cassettes

2. **Network Errors in CI**
   - Verify cassettes are properly loaded
   - Check that VCR is enabled (not turned off)
   - Ensure network group is excluded: `--exclude-group=network`

3. **Test Failures After API Changes**
   - Refresh cassettes to record new responses
   - Update test assertions to match new API behavior

### Verbose Output

```bash
./vendor/bin/phpunit --testdox
```

There is no debug switch for the test run itself. `DEBUG_TESTS` exists only in
`tests/bootstrap.php`, which PHPUnit does not load (the bootstrap is
`tests/fixtures/vcr-bootstrap.php`), and `phpunit.xml` pins it to `false` — setting it
has no effect. To see SOAP detail, enable debug mode on the client configuration inside
the test itself (`ClientConfiguration::test()` already does).

## Best Practices

1. **Keep Tests Fast**: Unit tests should run in milliseconds
2. **Use VCR**: Don't make real network calls in CI
3. **Test Edge Cases**: Include boundary conditions and error scenarios
4. **Mock External Dependencies**: Use PHPUnit mocks for unit tests
5. **Group Related Tests**: Use `@group` annotations for organization
6. **Document Complex Tests**: Add comments explaining test scenarios
7. **Maintain Cassettes**: Periodically refresh to catch API changes

## Test Coverage

Generate code coverage reports:

```bash
# Generate HTML coverage report
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html coverage/

# View coverage in browser
open coverage/index.html
```

Target coverage goals:
- Overall: >80%
- Critical paths: >90%
- DTOs and value objects: 100%