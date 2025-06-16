<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\EventListener;

use Netresearch\EuVatSdk\EventListener\FaultEventListener;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SoapFault;

/**
 * Test FaultEventListener SOAP fault mapping
 */
class FaultEventListenerTest extends TestCase
{
    private LoggerInterface $logger;
    private FaultEventListener $listener;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new FaultEventListener($this->logger);
    }

    public function testHandleTedb100FaultMapsToInvalidRequestException(): void
    {
        $fault = new SoapFault('TEDB-100', 'Invalid date format');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('SOAP Fault received from EU VAT service', $this->callback(function ($context) {
                return $context['fault_code'] === 'TEDB-100'
                    && $context['fault_string'] === 'Invalid date format';
            }));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid date format provided (TEDB-100): Invalid date format');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleTedb101FaultMapsToInvalidRequestException(): void
    {
        $fault = new SoapFault('TEDB-101', 'Invalid country code');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid country code provided (TEDB-101): Invalid country code');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleTedb102FaultMapsToInvalidRequestException(): void
    {
        $fault = new SoapFault('TEDB-102', 'Empty member states array');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Empty member states array provided (TEDB-102): Empty member states array');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleTedb400FaultMapsToServiceUnavailableException(): void
    {
        $fault = new SoapFault('TEDB-400', 'Internal server error');

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Internal application error in EU VAT service (TEDB-400): Internal server error');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleUnknownFaultMapsToSoapFaultException(): void
    {
        $fault = new SoapFault('UNKNOWN-500', 'Unknown error');

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessage('SOAP fault occurred (UNKNOWN-500): Unknown error');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleFaultWithoutFaultCodeUsesDefault(): void
    {
        $fault = new SoapFault('Client', 'No fault code');
        // Unset faultcode to test fallback
        unset($fault->faultcode);

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessage('SOAP fault occurred (UNKNOWN): No fault code');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleFaultWithoutFaultStringUsesDefault(): void
    {
        $fault = new SoapFault('TEDB-100', '');
        // Unset faultstring to test fallback
        unset($fault->faultstring);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid date format provided (TEDB-100): No fault string provided');

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleFaultWithDetailLogsDetail(): void
    {
        $fault = new SoapFault('TEDB-100', 'Invalid date');
        $fault->detail = 'Additional error information';

        $this->logger->expects($this->once())
            ->method('error')
            ->with('SOAP Fault received from EU VAT service', $this->callback(function ($context) {
                return $context['fault_detail'] === 'Additional error information';
            }));

        $this->expectException(InvalidRequestException::class);

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleFaultPreservesOriginalFaultAsPrevious(): void
    {
        $fault = new SoapFault('TEDB-100', 'Test error');

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected exception was not thrown');
        } catch (InvalidRequestException $e) {
            $this->assertSame($fault, $e->getPrevious());
        }
    }

    public function testIsClientValidationErrorIdentifiesClientErrors(): void
    {
        $this->assertTrue($this->listener->isClientValidationError('TEDB-100'));
        $this->assertTrue($this->listener->isClientValidationError('TEDB-101'));
        $this->assertTrue($this->listener->isClientValidationError('TEDB-102'));
        $this->assertFalse($this->listener->isClientValidationError('TEDB-400'));
        $this->assertFalse($this->listener->isClientValidationError('UNKNOWN'));
    }

    public function testIsServerErrorIdentifiesServerErrors(): void
    {
        $this->assertTrue($this->listener->isServerError('TEDB-400'));
        $this->assertFalse($this->listener->isServerError('TEDB-100'));
        $this->assertFalse($this->listener->isServerError('TEDB-101'));
        $this->assertFalse($this->listener->isServerError('UNKNOWN'));
    }

    public function testExtractErrorDetailsWithNull(): void
    {
        $details = $this->listener->extractErrorDetails(null);
        $this->assertEquals([], $details);
    }

    public function testExtractErrorDetailsWithArray(): void
    {
        $detail = ['error' => 'test', 'code' => 123];
        $details = $this->listener->extractErrorDetails($detail);
        $this->assertEquals($detail, $details);
    }

    public function testExtractErrorDetailsWithObject(): void
    {
        $detail = (object) ['error' => 'test', 'code' => 123];
        $details = $this->listener->extractErrorDetails($detail);
        $this->assertEquals(['error' => 'test', 'code' => 123], $details);
    }

    public function testExtractErrorDetailsWithValidXmlString(): void
    {
        $xmlDetail = '<error><code>123</code><message>Test error</message></error>';
        $details = $this->listener->extractErrorDetails($xmlDetail);

        $this->assertArrayHasKey('element_name', $details);
        $this->assertEquals('error', $details['element_name']);
        $this->assertArrayHasKey('text_content', $details);
        $this->assertStringContainsString('123', $details['text_content']);
        $this->assertStringContainsString('Test error', $details['text_content']);
    }

    public function testExtractErrorDetailsWithInvalidXmlString(): void
    {
        $invalidXml = 'This is not XML';
        $details = $this->listener->extractErrorDetails($invalidXml);

        $this->assertEquals(['raw_detail' => 'This is not XML'], $details);
    }

    public function testExtractErrorDetailsWithNonStringType(): void
    {
        $details = $this->listener->extractErrorDetails(123);
        $this->assertEquals(['raw_detail' => 123], $details);
    }
}
