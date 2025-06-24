# EU VAT SOAP SDK - Technical Specification

## Overview

This SDK provides a framework-agnostic PHP client library for interacting with the European Union's official VAT Retrieval Service SOAP API. It offers a clean abstraction layer with DTOs and interfaces for easy integration into any PHP application.

**Package Name:** `netresearch/sdk-eu-vat`  
**PHP Namespace:** `Netresearch\EuVatSdk`  
**Target PHP Version:** 8.1+  
**Framework:** Agnostic (no framework dependencies)  
**Distribution:** Composer package

## EU VAT Retrieval Service Details

### Service Information
- **WSDL URL:** https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService.wsdl
- **Production Endpoint:** https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService
- **Test Endpoint:** https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService-ACC
- **SOAP Version:** 1.1
- **Style:** Document/literal
- **Authentication:** None required

### Available Operations
- **`retrieveVatRates`** - Retrieve VAT rates for specified EU member states on a given date

## SDK Architecture

### Core Components

```
src/
├── Client/
│   ├── VatRetrievalClientInterface.php
│   ├── SoapVatRetrievalClient.php
│   └── ClientConfiguration.php
├── DTO/
│   ├── Request/
│   │   └── VatRatesRequest.php
│   └── Response/
│       ├── VatRatesResponse.php
│       ├── VatRateResult.php
│       └── VatRate.php
├── Exception/
│   ├── VatServiceException.php
│   ├── SoapFaultException.php
│   ├── InvalidRequestException.php
│   ├── ServiceUnavailableException.php
│   ├── ConfigurationException.php
│   ├── ParseException.php
│   ├── ValidationException.php
│   └── UnexpectedResponseException.php
├── EventListener/
│   ├── FaultEventListener.php
│   ├── RequestEventListener.php
│   └── ResponseEventListener.php
├── TypeConverter/
│   ├── DateTypeConverter.php
│   └── BigDecimalTypeConverter.php
├── Middleware/
│   └── LoggingMiddleware.php
├── Factory/
│   └── VatRetrievalClientFactory.php
└── resources/
    └── VatRetrievalService.wsdl
```

## Interface Design

### Primary Interface

```php
<?php

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\VatServiceException;

interface VatRetrievalClientInterface
{
    /**
     * Retrieve VAT rates for specified member states on a given date
     *
     * @param VatRatesRequest $request
     * @return VatRatesResponse
     * @throws VatServiceException
     */
    public function retrieveVatRates(VatRatesRequest $request): VatRatesResponse;
}
```

### Configuration Class

```php
<?php

namespace Netresearch\EuVatSdk\Client;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ClientConfiguration
{
    public function __construct(
        private string $endpoint = 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService',
        private array $soapOptions = [],
        private int $timeout = 30,
        private bool $debug = false,
        private LoggerInterface $logger = new NullLogger(),
        private ?string $wsdlPath = null
    ) {
        // Ensure timeout is set in soapOptions for consistency
        $this->soapOptions['connection_timeout'] ??= $this->timeout;
    }
    
    // Getters and factory methods...
    public function getEndpoint(): string;
    public function getSoapOptions(): array;
    public function getTimeout(): int;
    public function isDebug(): bool;
    public function getLogger(): LoggerInterface;
    public function getWsdlPath(): ?string;
    
    public static function production(?LoggerInterface $logger = null): self;
    public static function test(?LoggerInterface $logger = null): self;
}
```

## Data Transfer Objects (DTOs)

### Request DTO

```php
<?php

namespace Netresearch\EuVatSdk\DTO\Request;

use DateTimeInterface;
use Netresearch\EuVatSdk\Exception\ValidationException;

class VatRatesRequest
{
    /**
     * @param string[] $memberStates Array of ISO 3166-1 alpha-2 country codes
     * @param DateTimeInterface $situationOn Date for which rates are requested
     * @throws ValidationException
     */
    public function __construct(
        private array $memberStates,
        private DateTimeInterface $situationOn
    ) {
        $this->validateMemberStates($memberStates);
    }
    
    public function getMemberStates(): array;
    public function getSituationOn(): DateTimeInterface;
    
    /**
     * @throws ValidationException
     */
    private function validateMemberStates(array $memberStates): void
    {
        if (empty($memberStates)) {
            throw new ValidationException('Member states array cannot be empty.');
        }
        
        foreach ($memberStates as $code) {
            if (!is_string($code) || strlen($code) !== 2) {
                throw new ValidationException("Invalid member state code provided: {$code}");
            }
        }
    }
}
```

### Response DTOs

```php
<?php

namespace Netresearch\EuVatSdk\DTO\Response;

class VatRatesResponse
{
    /**
     * @param VatRateResult[] $results
     */
    public function __construct(
        private array $results
    ) {}
    
    /**
     * @return VatRateResult[]
     */
    public function getResults(): array;
    
    /**
     * Get results filtered by country
     * @param string $countryCode
     * @return VatRateResult[]
     */
    public function getResultsForCountry(string $countryCode): array;
    
    /**
     * Get results filtered by category
     * @param string $category
     * @return VatRateResult[]
     */
    public function getResultsByCategory(string $category): array;
}
```

```php
<?php

namespace Netresearch\EuVatSdk\DTO\Response;

use DateTimeInterface;

class VatRateResult
{
    public function __construct(
        private string $memberState,
        private VatRate $rate,
        private DateTimeInterface $situationOn,
        private ?string $comment = null
    ) {}
    
    public function getMemberState(): string;
    public function getRate(): VatRate;
    public function getSituationOn(): DateTimeInterface;
    public function getComment(): ?string;
}
```

```php
<?php

namespace Netresearch\EuVatSdk\DTO\Response;

use Brick\Math\BigDecimal;

class VatRate
{
    private readonly BigDecimal $decimalValue;
    
    public function __construct(
        private string $type,           // STANDARD, REDUCED, REDUCED[1], etc.
        string $value,                  // Percentage value as string (e.g., "19.0")
        private ?string $category = null // Official category identifier (e.g., FOODSTUFFS)
    ) {
        $this->decimalValue = BigDecimal::of($value);
    }
    
    public function getType(): string;
    
    /**
     * Get the raw string value as received from the API
     */
    public function getValue(): string
    {
        return $this->decimalValue->__toString();
    }
    
    /**
     * Get the value as a BigDecimal for precise calculations
     */
    public function getValue(): BigDecimal
    {
        return $this->decimalValue;
    }
    
    /**
     * Get the value as float (use with caution for calculations)
     * @deprecated Use getValue() for precise calculations
     */
    public function getValueAsFloat(): float
    {
        return $this->decimalValue->toFloat();
    }
    
    public function getCategory(): ?string;
    
    public function isStandard(): bool
    {
        return $this->type === 'STANDARD';
    }
    
    public function isReduced(): bool
    {
        return str_starts_with($this->type, 'REDUCED');
    }
    
    public function isSuperReduced(): bool
    {
        return $this->type === 'SUPER_REDUCED';
    }
    
    public function isParkingRate(): bool
    {
        return $this->type === 'PK' || $this->type === 'PARKING';
    }
    
    public function isZeroRate(): bool
    {
        return $this->type === 'Z' || $this->type === 'ZERO';
    }
    
    public function isExempt(): bool
    {
        return $this->type === 'E' || $this->type === 'EXEMPT';
    }
}
```

## Exception Hierarchy

```php
<?php

namespace Netresearch\EuVatSdk\Exception;

abstract class VatServiceException extends \Exception
{
    // Base exception for all SDK errors
}

class SoapFaultException extends VatServiceException
{
    public function __construct(
        string $message,
        private string $faultCode,
        private string $faultString,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
    
    public function getFaultCode(): string;
    public function getFaultString(): string;
}

class InvalidRequestException extends VatServiceException
{
    // For client-side validation errors and invalid API requests (TEDB-100, TEDB-101, TEDB-102)
}

class ServiceUnavailableException extends VatServiceException
{
    // For network/service availability issues
}

class ConfigurationException extends VatServiceException
{
    // For invalid configuration (malformed WSDL path, invalid endpoints, etc.)
}

class ParseException extends VatServiceException
{
    // For SOAP response parsing errors
}

class ValidationException extends VatServiceException
{
    // For DTO validation failures
}

class UnexpectedResponseException extends VatServiceException
{
    // For unexpected API responses that don't match expected schema
}
```

## SOAP Client Implementation

### Core Implementation

```php
<?php

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\ParseException;
use Soap\Engine\Engine;
use Soap\Engine\SimpleEngine;
use Soap\ExtSoapEngine\ExtSoapDriver;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMapCollection;
use Soap\ExtSoapEngine\Configuration\ClassMap\ClassMap;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterCollection;
use Soap\ExtSoapEngine\ExtSoapOptions;
use Soap\ExtSoapEngine\Exception\TransportException;
use Soap\ExtSoapEngine\Exception\WsdlException;
use Netresearch\EuVatSdk\TypeConverter\DateTypeConverter;
use Netresearch\EuVatSdk\TypeConverter\BigDecimalTypeConverter;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SoapVatRetrievalClient implements VatRetrievalClientInterface
{
    private const LOCAL_WSDL_PATH = __DIR__ . '/../../resources/VatRetrievalService.wsdl';
    
    private Engine $engine;
    
    public function __construct(
        private ClientConfiguration $config
    ) {
        $this->initializeEngine();
    }
    
    public function retrieveVatRates(VatRatesRequest $request): VatRatesResponse
    {
        try {
            // With ClassMap, the engine expects the DTO directly.
            // It automatically converts it to the SOAP request structure.
            // The return type will be the fully-hydrated VatRatesResponse DTO.
            return $this->engine->request('retrieveVatRates', [$request]);
        } catch (\SoapFault $fault) {
            // Fallback handling - FaultEventListener should have caught this
            throw new SoapFaultException(
                $fault->getMessage(),
                $fault->faultcode,
                $fault->faultstring,
                $fault
            );
        } catch (TransportException $e) {
            throw new ServiceUnavailableException(
                'Network error occurred while connecting to EU VAT service',
                0,
                $e
            );
        } catch (WsdlException $e) {
            throw new ConfigurationException(
                'WSDL parsing error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    private function initializeEngine(): void
    {
        // 1. Define the ClassMap to map WSDL types to PHP DTOs
        $classMap = new ClassMapCollection([
            new ClassMap('retrieveVatRates', VatRatesRequest::class),
            new ClassMap('retrieveVatRatesResponse', VatRatesResponse::class),
            new ClassMap('vatRateResult', VatRateResult::class),
            new ClassMap('vatRate', VatRate::class),
        ]);

        // 2. Define TypeConverters for custom data types
        $typeConverters = new TypeConverterCollection([
            new DateTypeConverter(), // Converts xsd:date to DateTimeImmutable
            new BigDecimalTypeConverter(), // Converts xsd:decimal to Brick\Math\BigDecimal
        ]);

        // 3. Create ExtSoapOptions with the ClassMap and TypeConverters
        $wsdlPath = $this->config->getWsdlPath() ?? self::LOCAL_WSDL_PATH;
        $options = ExtSoapOptions::defaults($wsdlPath, [
            'location' => $this->config->getEndpoint(),
            'connection_timeout' => $this->config->getTimeout(),
            ...$this->config->getSoapOptions()
        ])
            ->withClassMap($classMap)
            ->withTypeConverters($typeConverters);

        // 4. Create EventDispatcher and register listeners
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new FaultEventListener());
        if ($this->config->isDebug()) {
            $dispatcher->addSubscriber(new RequestEventListener($this->config->getLogger()));
            $dispatcher->addSubscriber(new ResponseEventListener($this->config->getLogger()));
        }

        // 5. Create the Engine
        $driver = ExtSoapDriver::createFromOptions($options);
        $this->engine = new SimpleEngine($driver, $dispatcher);
    }
}
```

## Factory Pattern

```php
<?php

namespace Netresearch\EuVatSdk\Factory;

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Netresearch\EuVatSdk\Client\VatRetrievalClientInterface;
use Psr\Log\LoggerInterface;

class VatRetrievalClientFactory
{
    public static function create(
        ?ClientConfiguration $config = null,
        ?LoggerInterface $logger = null
    ): VatRetrievalClientInterface {
        $config ??= ClientConfiguration::production($logger);
        return new SoapVatRetrievalClient($config);
    }
    
    public static function createForTesting(?LoggerInterface $logger = null): VatRetrievalClientInterface
    {
        return new SoapVatRetrievalClient(ClientConfiguration::test($logger));
    }
}
```

## TypeConverter Classes

### Custom Type Handling

The SDK uses TypeConverters to automatically handle conversion between SOAP types and PHP objects:

```php
<?php

namespace Netresearch\EuVatSdk\TypeConverter;

use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;
use DateTimeImmutable;
use DateTimeInterface;

class DateTypeConverter implements TypeConverterInterface
{
    public function getTypeName(): string
    {
        return 'dateTime';
    }

    public function convertXmlToPhp(string $data)
    {
        return new DateTimeImmutable($data);
    }

    public function convertPhpToXml($data): string
    {
        if ($data instanceof DateTimeInterface) {
            return $data->format('Y-m-d\TH:i:s');
        }
        
        return (string) $data;
    }
}
```

```php
<?php

namespace Netresearch\EuVatSdk\TypeConverter;

use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Netresearch\EuVatSdk\Exception\ParseException;

class BigDecimalTypeConverter implements TypeConverterInterface
{
    public function getTypeName(): string
    {
        return 'decimal';
    }

    public function convertXmlToPhp(string $data)
    {
        try {
            return BigDecimal::of($data);
        } catch (MathException $e) {
            throw new ParseException(
                "Failed to parse decimal value: {$data}",
                0,
                $e
            );
        }
    }

    public function convertPhpToXml($data): string
    {
        if ($data instanceof BigDecimal) {
            return $data->__toString();
        }
        
        return (string) $data;
    }
}
```

## Event-Driven Architecture

### Event System with php-soap/ext-soap-engine

The SDK leverages the event system provided by php-soap/ext-soap-engine for extensibility:

### Event Listeners

```php
<?php

namespace Netresearch\EuVatSdk\EventListener;

use Soap\Engine\Event\FaultEvent;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Psr\Log\LoggerInterface;

class FaultEventListener
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public function onFault(FaultEvent $event): void
    {
        $fault = $event->getFault();
        $faultCode = $fault->faultcode ?? 'unknown';
        
        $this->logger?->error('SOAP Fault received', [
            'faultCode' => $faultCode,
            'faultString' => $fault->faultstring ?? '',
            'detail' => $fault->detail ?? null
        ]);
        
        // Map EU VAT service specific fault codes to domain exceptions
        $exception = match ($faultCode) {
            'TEDB-100' => new InvalidRequestException('Invalid date format provided'),
            'TEDB-101' => new InvalidRequestException('Invalid country code provided'),
            'TEDB-102' => new InvalidRequestException('Empty member states array provided'),
            'TEDB-400' => new ServiceUnavailableException('Internal application error in EU VAT service'),
            default => new SoapFaultException(
                $fault->faultstring ?? 'Unknown SOAP fault',
                $faultCode,
                $fault->faultstring ?? '',
                $fault
            )
        };
        
        throw $exception;
    }
}

class RequestEventListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onRequest(RequestEvent $event): void
    {
        $this->logger->debug('EU VAT SOAP Request', [
            'method' => $event->getMethod(),
            'arguments' => $event->getArguments()
        ]);
    }
}

class ResponseEventListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onResponse(ResponseEvent $event): void
    {
        $this->logger->debug('EU VAT SOAP Response received', [
            'method' => $event->getMethod(),
            'responseType' => get_class($event->getResponse())
        ]);
    }
}
```

### Middleware Support

```php
<?php

namespace Netresearch\EuVatSdk\Middleware;

use Soap\Engine\Middleware\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function beforeRequest(string $method, array $arguments): array
    {
        $this->logger->info('EU VAT SOAP Request initiated', [
            'method' => $method,
            'argumentCount' => count($arguments)
        ]);
        
        return $arguments; // Return unmodified arguments
    }
    
    public function afterResponse($result)
    {
        $this->logger->info('EU VAT SOAP Response processed', [
            'resultType' => get_class($result)
        ]);
        
        return $result; // Return unmodified result
    }
}
```

**Note:** Authentication middleware is not needed as the EU VAT service requires no authentication.

### Benefits of Event-Driven Approach

- **Decoupling:** Core logic separated from cross-cutting concerns
- **Extensibility:** Easy to add new behaviors without modifying core
- **Testability:** Event listeners and middleware can be tested in isolation
- **Flexibility:** Users can register custom listeners and middleware
- **Built-in Support:** Leverages battle-tested Symfony EventDispatcher

### Event Listeners vs Middleware

- **Event Listeners:** Handle specific events (faults, requests, responses) and can transform exceptions or log detailed information
- **Middleware:** Wrap the entire request/response cycle and are better suited for cross-cutting concerns like timing, authentication, or general logging
- **TypeConverters:** Handle automatic data type conversion between SOAP XML and PHP objects, eliminating manual parsing code

## Usage Examples

### Basic Usage

```php
<?php

use Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Brick\Math\BigDecimal;

// Create client
$client = VatRetrievalClientFactory::create();

// Build request
$request = new VatRatesRequest(
    memberStates: ['DE', 'FR', 'IT'],
    situationOn: new \DateTime('2024-01-01')
);

// Execute request
try {
    $response = $client->retrieveVatRates($request);
    
    foreach ($response->getResults() as $result) {
        $rate = $result->getRate();
        
        echo sprintf(
            "Country: %s, Type: %s, Rate: %s%%, Category: %s\n",
            $result->getMemberState(),
            $rate->getType(),
            $rate->getValue(), // Precise decimal string
            $rate->getCategory() ?? 'N/A'
        );
        
        // For calculations, use BigDecimal
        $decimalRate = $rate->getValue();
        $amount = BigDecimal::of('100.00');
        $vatAmount = $amount->multipliedBy($decimalRate)->dividedBy(100, 2);
        echo "VAT on €100.00: €{$vatAmount}\n";
    }
} catch (\Netresearch\EuVatSdk\Exception\ValidationException $e) {
    echo "Validation Error: " . $e->getMessage();
} catch (\Netresearch\EuVatSdk\Exception\InvalidRequestException $e) {
    echo "Invalid Request: " . $e->getMessage();
} catch (\Netresearch\EuVatSdk\Exception\ServiceUnavailableException $e) {
    echo "Service Unavailable: " . $e->getMessage();
} catch (\Netresearch\EuVatSdk\Exception\VatServiceException $e) {
    echo "General Error: " . $e->getMessage();
}
```

### Advanced Configuration

```php
<?php

use Netresearch\EuVatSdk\Client\ClientConfiguration;
use Netresearch\EuVatSdk\Client\SoapVatRetrievalClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Custom logger
$logger = new Logger('eu-vat-sdk');
$logger->pushHandler(new StreamHandler('/path/to/vat-sdk.log', Logger::DEBUG));

// Custom configuration
$config = new ClientConfiguration(
    endpoint: 'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService-ACC',
    soapOptions: [
        'connection_timeout' => 60,
        'cache_wsdl' => WSDL_CACHE_MEMORY,
        'trace' => true
    ],
    timeout: 60,
    debug: true,
    logger: $logger,
    wsdlPath: '/custom/path/to/VatRetrievalService.wsdl'
);

$client = new SoapVatRetrievalClient($config);
```

## Error Handling Strategy

### SOAP Fault Mapping
Based on EU service specification:
- **TEDB-100:** Invalid date format → `InvalidRequestException`
- **TEDB-101:** Invalid country code → `InvalidRequestException`  
- **TEDB-102:** Empty member states → `InvalidRequestException`
- **TEDB-400:** Internal application error → `SoapFaultException`

### Event-Driven Error Handling
The SDK uses php-soap/ext-soap-engine's event system:
- **FaultEvent:** All SOAP faults are caught and transformed to domain exceptions
- **RequestEvent:** Opportunity to log/modify outgoing requests
- **ResponseEvent:** Opportunity to log/transform responses
- **Middleware:** Built-in middleware system for cross-cutting concerns

## Testing Strategy

### Unit Tests
- Mock SOAP responses for different scenarios
- Test DTO validation and transformation
- Exception handling coverage
- BigDecimal precision testing

### Integration Tests
- Test against EU acceptance environment
- Validate real SOAP communication
- Performance and timeout testing

### Static Analysis
- **PHPStan Level 8+:** Strict type checking and error detection
- **Rector Rules:** PHP 8.1+ modernization and best practices
- **Custom Rules:** Financial calculation validation

### Test Data
- Valid/invalid country codes
- Date boundary testing
- Empty response scenarios
- Decimal precision edge cases

## Performance Considerations

### Optimization Features
- **WSDL Caching:** Configure appropriate caching strategy
- **Connection Pooling:** Reuse SOAP client instances
- **Request Batching:** Support multiple countries per request
- **Response Caching:** Optional response caching layer

### Memory Management
- Streaming for large responses (if needed)
- Proper resource cleanup
- Memory-efficient DTO design

## Dependencies

### Required
- **PHP:** ^8.1
- **ext-soap:** * (Required for SOAP client functionality)
- **ext-libxml:** * (Required for XML processing)
- **php-soap/ext-soap-engine:** ^1.7 (Modern SOAP client engine)
- **psr/log:** ^3.0 (PSR-3 logging interface)
- **brick/math:** ^0.12 (Arbitrary-precision arithmetic for financial calculations)

### Development
- **phpunit/phpunit:** ^10.0
- **phpstan/phpstan:** ^1.10 (Static analysis)
- **rector/rector:** ^1.0 (Code modernization and refactoring)
- **squizlabs/php_codesniffer:** ^3.7 (Code style checking)
- **monolog/monolog:** ^3.0 (Development and testing)

## Installation & Distribution

### Composer Configuration

```json
{
    "name": "netresearch/sdk-eu-vat",
    "description": "PHP SDK for EU VAT Retrieval Service SOAP API",
    "type": "library",
    "license": "MIT",
    "keywords": ["eu", "vat", "soap", "tax", "api", "netresearch"],
    "require": {
        "php": "^8.1",
        "ext-soap": "*",
        "ext-libxml": "*",
        "php-soap/ext-soap-engine": "^1.7",
        "psr/log": "^3.0",
        "brick/math": "^0.12"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10",
        "rector/rector": "^1.0",
        "squizlabs/php_codesniffer": "^3.7",
        "monolog/monolog": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "Netresearch\\EuVatSdk\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Netresearch\\EuVatSdk\\Tests\\": "tests/"
        }
    },
    "suggest": {
        "monolog/monolog": "For advanced logging capabilities"
    }
}
```

### PSR Compliance & Quality Tools
- **PSR-4:** Autoloading
- **PSR-12:** Coding style (enforced by PHP_CodeSniffer)
- **PSR-3:** Logger interface (core feature)
- **PSR-14:** Event Dispatcher (via Symfony EventDispatcher)

### Development Workflow
- **PHPStan:** Level 8+ static analysis for type safety
- **Rector:** Automated code modernization and PHP version upgrades
- **PHPUnit:** Comprehensive unit and integration testing
- **PHP_CodeSniffer:** PSR-12 compliance checking

## Versioning & Releases

### Semantic Versioning
- **Major:** Breaking API changes
- **Minor:** New features, backward compatible
- **Patch:** Bug fixes

### Backward Compatibility
- Maintain interface stability
- Deprecation warnings before breaking changes
- Clear migration guides

---

**Implementation Priority:** High  
**Estimated Complexity:** Medium  
**Target Release:** v1.0.0