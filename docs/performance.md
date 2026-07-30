# Performance Guide

## Performance characteristics

No benchmark figures are published for this SDK: none are measured in CI, and the
numbers that matter are dominated by things outside the library.

- Response time is dominated by the round trip to the EU service, not by SDK overhead
- Memory scales with the number of result rows (one row per rate type per member state)
- WSDL caching removes the schema parse from every call after the first

Measure against your own network and workload before deriving timeouts or SLOs from any
of this.

## Optimization Tips

### Enable WSDL Caching
```php
// Enable WSDL caching in production
ini_set('soap.wsdl_cache_enabled', '1');
ini_set('soap.wsdl_cache_dir', '/tmp');
ini_set('soap.wsdl_cache_ttl', '86400'); // 24 hours
```

### Batch Requests
```php
// Instead of multiple single-country requests
$request = new VatRatesRequest(['DE', 'FR', 'IT'], new DateTime('2024-01-01'));
$response = $client->retrieveVatRates($request);
```

### Application-Level Caching
```php
// Cache frequently accessed rates
$cacheKey = 'vat_rates_' . implode('_', $memberStates) . '_' . $date->format('Y-m-d');

if (!$cache->has($cacheKey)) {
    $response = $client->retrieveVatRates($request);
    $cache->set($cacheKey, $response, 3600); // Cache for 1 hour
}

return $cache->get($cacheKey);
```

### Timeout Configuration
```php
// Adjust timeouts based on your requirements
$config = ClientConfiguration::production($logger)
    ->withTimeout(5); // 5 seconds for time-critical applications
```

## Memory Usage

The SDK is designed for efficiency:
- DTOs are immutable to prevent memory leaks
- BigDecimal objects are lightweight
- No persistent state between requests

Connections are not pooled or kept alive: each client owns one ext-soap client, and
every call is a fresh HTTP request. Reuse a single client instance rather than building
one per call, so the WSDL is parsed once.
