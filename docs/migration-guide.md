# Migration Guide

## From Legacy VAT Services

If you're migrating from a legacy VAT service implementation:

1. **Replace direct SOAP calls** with the SDK client:
   ```php
   // Old approach
   $soapClient = new SoapClient($wsdlUrl);
   $result = $soapClient->retrieveVatRates($params);
   
   // New approach
   $client = VatRetrievalClientFactory::create();
   $request = new VatRatesRequest(['DE'], new DateTime('2024-01-01'));
   $response = $client->retrieveVatRates($request);
   ```

2. **Update error handling** to use typed exceptions:
   ```php
   // Old approach
   try {
       $result = $soapClient->call();
   } catch (SoapFault $e) {
       // Generic error handling
   }
   
   // New approach
   try {
       $response = $client->retrieveVatRates($request);
   } catch (InvalidRequestException $e) {
       // Handle validation errors
   } catch (ServiceUnavailableException $e) {
       // Handle service issues
   }
   ```

3. **Migrate to BigDecimal** for financial calculations:
   ```php
   // Old approach (floating point issues)
   $vatAmount = $netAmount * ($vatRate / 100);
   
   // New approach (precise)
   $net = BigDecimal::of($netAmount);
   $rate = BigDecimal::of($vatRate);
   $vatAmount = $net->multipliedBy($rate)->dividedBy('100', 2);
   ```