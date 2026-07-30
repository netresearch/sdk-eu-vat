# Security Considerations

## Supported Security Features
- Input validation and sanitization
- SOAP injection protection
- Secure error message handling
- TLS/SSL for all communications
- Request logging limited to member state codes, dates and the endpoint URL — unless
  debug mode is enabled, which makes ext-soap retain the full SOAP envelopes (see
  [Logging](#logging))

## Security Updates
This section will be updated with any security-related changes or advisories.

## Best Practices

### Input Validation
The SDK automatically validates all inputs, but you should still validate data before passing it to the SDK:

```php
// Validate member state codes
$validStates = ['DE', 'FR', 'IT', /* ... */];
$requestStates = array_intersect($userInput, $validStates);

$request = new VatRatesRequest($requestStates, new DateTime('2024-01-01'));
```

### Error Handling
Never expose internal error details to end users:

```php
try {
    $response = $client->retrieveVatRates($request);
} catch (VatServiceException $e) {
    // Log detailed error for debugging
    $logger->error('VAT service error', ['exception' => $e]);
    
    // Return generic message to user
    throw new UserFriendlyException('VAT rates temporarily unavailable');
}
```

### Logging
The SDK's own log records carry only the member states, the requested date, the result
count and the endpoint URL. Debug mode is the exception: it sets the ext-soap `trace`
option, so the SOAP client keeps the complete request and response envelopes in memory
for inspection. `ClientConfiguration::production()` has debug off and
`ClientConfiguration::test()` has it on, so keep production configurations on the
production factory — and verify your own logging configuration:

```php
$config = ClientConfiguration::production($logger)
    ->withDebug(false); // Off by default for production(); on for test()
```
