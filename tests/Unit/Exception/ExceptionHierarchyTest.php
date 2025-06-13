<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Exception;

use Netresearch\EuVatSdk\Exception\ConfigurationException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ParseException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\UnexpectedResponseException;
use Netresearch\EuVatSdk\Exception\ValidationException;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Test the exception hierarchy structure
 */
class ExceptionHierarchyTest extends TestCase
{
    /**
     * Test that all exceptions extend VatServiceException
     */
    public function testAllExceptionsExtendVatServiceException(): void
    {
        $exceptions = [
            new ConfigurationException('test'),
            new InvalidRequestException('test'),
            new ParseException('test'),
            new ServiceUnavailableException('test'),
            new UnexpectedResponseException('test'),
            new ValidationException('test'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(VatServiceException::class, $exception);
            $this->assertInstanceOf(Exception::class, $exception);
        }
    }

    /**
     * Test SoapFaultException specific functionality
     */
    public function testSoapFaultExceptionGetters(): void
    {
        $message = 'SOAP fault occurred';
        $faultCode = 'SOAP-ENV:Client';
        $faultString = 'Invalid request format';
        $previous = new Exception('Previous exception');

        $exception = new SoapFaultException($message, $faultCode, $faultString, $previous);

        $this->assertInstanceOf(VatServiceException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($faultCode, $exception->getFaultCode());
        $this->assertEquals($faultString, $exception->getFaultString());
        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * Test that VatServiceException cannot be instantiated directly
     */
    public function testVatServiceExceptionIsAbstract(): void
    {
        $reflection = new \ReflectionClass(VatServiceException::class);
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test exception messages are preserved
     */
    public function testExceptionMessagesArePreserved(): void
    {
        $testCases = [
            [ConfigurationException::class, 'Invalid WSDL path'],
            [InvalidRequestException::class, 'Invalid country code: XX'],
            [ParseException::class, 'Failed to parse decimal value'],
            [ServiceUnavailableException::class, 'Service timeout'],
            [UnexpectedResponseException::class, 'Missing required field'],
            [ValidationException::class, 'Empty member states array'],
        ];

        foreach ($testCases as [$exceptionClass, $message]) {
            $exception = new $exceptionClass($message);
            $this->assertEquals($message, $exception->getMessage());
        }
    }

    /**
     * Test error code functionality in InvalidRequestException
     */
    public function testInvalidRequestExceptionErrorCode(): void
    {
        // Without error code
        $exception = new InvalidRequestException('Invalid request');
        $this->assertNull($exception->getErrorCode());

        // With error code
        $exception = new InvalidRequestException('Invalid date format provided', 'TEDB-100');
        $this->assertEquals('TEDB-100', $exception->getErrorCode());
        $this->assertEquals('Invalid date format provided', $exception->getMessage());

        // With previous exception
        $previous = new Exception('Previous error');
        $exception = new InvalidRequestException('Invalid country code provided', 'TEDB-101', $previous);
        $this->assertEquals('TEDB-101', $exception->getErrorCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * Test error code functionality in ServiceUnavailableException
     */
    public function testServiceUnavailableExceptionErrorCode(): void
    {
        // Without error code
        $exception = new ServiceUnavailableException('Service timeout');
        $this->assertNull($exception->getErrorCode());

        // With error code
        $exception = new ServiceUnavailableException('Internal application error in EU VAT service', 'TEDB-400');
        $this->assertEquals('TEDB-400', $exception->getErrorCode());
        $this->assertEquals('Internal application error in EU VAT service', $exception->getMessage());
    }
}