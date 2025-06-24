# Performance Guide

## Benchmarks
- Typical response time: ~10ms for single country requests
- Memory usage: ~2MB for full EU member state queries
- WSDL caching reduces initialization by ~100ms

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
- SOAP client reuses connections
- No persistent state between requests