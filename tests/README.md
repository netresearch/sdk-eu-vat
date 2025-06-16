# EU VAT SDK Test Suite

This directory contains the comprehensive test suite for the EU VAT SOAP SDK, including unit tests, integration tests, and performance benchmarks.

## Overview

The test suite is organized into three main categories:

- **Unit Tests** (`tests/Unit/`): Fast, isolated tests for individual components
- **Integration Tests** (`tests/Integration/`): Tests against the actual EU VAT service using php-vcr
- **Fixtures** (`tests/fixtures/`): Test data, VCR cassettes, and data providers

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

### Specific Test Groups
```bash
# Run tests that don't require network access
./vendor/bin/phpunit --exclude-group=network

# Run performance benchmarks
./vendor/bin/phpunit --group=performance

# Run slow tests
./vendor/bin/phpunit --group=slow
```

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

- `USE_PRODUCTION_ENDPOINT`: Set to `true` to test against production (default: `false` for test endpoint)
- `REFRESH_CASSETTES`: Set to `true` to force re-recording of VCR cassettes
- `DEBUG_TESTS`: Set to `true` to enable debug logging during tests

Example:
```bash
USE_PRODUCTION_ENDPOINT=true DEBUG_TESTS=true ./vendor/bin/phpunit
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
- Performance benchmarks
- Memory usage
- Timeout handling

### Test Data Providers

The `TestDataProvider` class in `tests/fixtures/` provides:

- Current EU member states list
- Known VAT rates for validation
- Test dates for various scenarios
- Country groupings for batch testing
- Edge case configurations

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
        
        $this->assertCount(1, $response->getResults());
    }
}
```

## Performance Testing

Performance benchmarks help ensure the SDK maintains acceptable performance characteristics:

```bash
# Run performance tests
./vendor/bin/phpunit --group=performance

# Performance metrics tracked:
# - Single request response time
# - Batch request efficiency
# - Memory usage
# - Concurrent request handling
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

### Debug Mode

Enable detailed logging during tests:

```bash
DEBUG_TESTS=true ./vendor/bin/phpunit --testdox
```

This will output:
- SOAP request/response details
- Performance metrics
- VCR recording status
- Detailed error information

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