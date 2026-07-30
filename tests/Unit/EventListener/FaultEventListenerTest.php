<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\EventListener;

use Netresearch\EuVatSdk\EventListener\FaultEventListener;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SoapFault;
use stdClass;

/**
 * Test FaultEventListener SOAP fault mapping
 *
 * The fixtures in this class mirror the fault the TEDB service actually sends,
 * taken verbatim from tests/fixtures/cassettes/error-invalid-country-code:
 *
 * ```xml
 * <faultcode>env:Client</faultcode>
 * <faultstring>TEDB-ERR-2 - Request is not valid</faultstring>
 * <detail><ns2:retrieveVatRatesFaultMsg><ns0:error>
 *   <ns0:code>00002</ns0:code>
 *   <ns0:description>The Member State "XX" does not exist.</ns0:description>
 * </ns0:error>…</ns2:retrieveVatRatesFaultMsg></detail>
 * ```
 */
class FaultEventListenerTest extends TestCase
{
    /**
     * Fault string exactly as recorded from the service
     */
    private const REAL_FAULT_STRING = 'TEDB-ERR-2 - Request is not valid';

    private LoggerInterface $logger;
    private FaultEventListener $listener;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new FaultEventListener($this->logger);
    }

    /**
     * Build the decoded fault detail as ext-soap hands it to the listener
     *
     * @param array<int, array{code: string, description: string}> $errors Error entries.
     */
    private function createFaultDetail(array $errors): stdClass
    {
        $decodedErrors = [];
        foreach ($errors as $error) {
            $decodedErrors[] = (object) $error;
        }

        $faultMsg = new stdClass();
        // ext-soap collapses a single repeated element into the object itself
        $faultMsg->error = count($decodedErrors) === 1 ? $decodedErrors[0] : $decodedErrors;

        $detail = new stdClass();
        $detail->retrieveVatRatesFaultMsg = $faultMsg;

        return $detail;
    }

    /**
     * Build the fault recorded for an invalid member state request
     */
    private function createRealClientFault(): SoapFault
    {
        $fault = new SoapFault('env:Client', self::REAL_FAULT_STRING);
        $fault->detail = $this->createFaultDetail([
            ['code' => '00002', 'description' => 'The Member State "XX" does not exist.'],
            ['code' => '00002', 'description' => 'The Member State "YY" does not exist.'],
        ]);

        return $fault;
    }

    public function testRecordedClientFaultMapsToInvalidRequestException(): void
    {
        $fault = $this->createRealClientFault();

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'SOAP Fault received from EU VAT service',
                $this->callback(fn($context): bool => $context['fault_code'] === 'env:Client'
                    && $context['fault_string'] === self::REAL_FAULT_STRING)
            );

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame('TEDB-ERR-2', $e->getErrorCode());
            $this->assertStringContainsString(self::REAL_FAULT_STRING, $e->getMessage());
            $this->assertStringContainsString('[00002] The Member State "XX" does not exist.', $e->getMessage());
            $this->assertStringContainsString('[00002] The Member State "YY" does not exist.', $e->getMessage());
        }
    }

    public function testRecordedClientFaultWithSingleErrorDetail(): void
    {
        $fault = new SoapFault('env:Client', self::REAL_FAULT_STRING);
        $fault->detail = $this->createFaultDetail([
            ['code' => '00002', 'description' => 'The Member State "US" does not exist.'],
        ]);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('[00002] The Member State "US" does not exist.');

        $this->listener->handleSoapFault($fault);
    }

    /**
     * A bare client fault code still counts as a rejection when the service signed it
     *
     * The TEDB identifier in the fault string is positive evidence that the fault
     * was produced by the service, whatever shape the fault code arrived in.
     */
    public function testBareClientFaultCodeWithTedbIdentifierMapsToInvalidRequestException(): void
    {
        $fault = new SoapFault('Client', self::REAL_FAULT_STRING);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request rejected by the EU VAT service (TEDB-ERR-2)');

        $this->listener->handleSoapFault($fault);
    }

    /**
     * ext-soap's own local failures must not be reported as request rejections
     *
     * These fault strings are ext-soap's wording, raised before or instead of any
     * service response: a proxy or captive portal answering with HTML, a truncated
     * body, an encoding failure. No request was rejected, and the condition is
     * usually transient, so the retryable exception is the correct contract.
     */
    #[DataProvider('localExtSoapFailureProvider')]
    public function testLocalExtSoapFailureMapsToServiceUnavailableException(string $faultString): void
    {
        $fault = new SoapFault('Client', $faultString);

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame('Client', $e->getErrorCode());
            $this->assertStringContainsString(
                'Communication with the EU VAT service failed before a service response could be read '
                . '(Client): ' . $faultString,
                $e->getMessage()
            );
            $this->assertStringNotContainsString('Invalid request', $e->getMessage());
        }
    }

    /**
     * Fault strings ext-soap raises for purely local failures
     *
     * @return array<string, array{string}>
     */
    public static function localExtSoapFailureProvider(): array
    {
        return [
            'non-XML response body' => ['looks like we got no XML document'],
            'HTML error page' => ['DTD are not supported by SOAP'],
            'non-string transport result' => ['SoapClient::__doRequest() returned non string value'],
            'serialization failure' => ['Encoding: object has no \'situationOn\' property'],
        ];
    }

    /**
     * A bare Sender fault code without service evidence is transport, not rejection
     */
    public function testBareSenderFaultCodeWithoutEvidenceMapsToServiceUnavailableException(): void
    {
        $fault = new SoapFault('Sender', 'looks like we got no XML document');

        $this->expectException(ServiceUnavailableException::class);

        $this->listener->handleSoapFault($fault);
    }

    /**
     * A fault code bound to an unrelated namespace is not a responsibility marker
     */
    public function testUnprefixedClientCodeInUnrelatedNamespaceIsNotClassified(): void
    {
        $fault = new SoapFault('Client', 'Client rejected the batch');
        $fault->faultcodens = 'urn:acme:codes';

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessage('SOAP fault occurred (Client): Client rejected the batch');

        $this->listener->handleSoapFault($fault);
    }

    /**
     * The SOAP envelope namespace ext-soap resolves for bare codes stays acceptable
     */
    public function testUnprefixedClientCodeInSoapEnvelopeNamespaceStaysClassified(): void
    {
        $fault = new SoapFault('Client', self::REAL_FAULT_STRING);
        $fault->faultcodens = 'http://schemas.xmlsoap.org/soap/envelope/';

        $this->expectException(InvalidRequestException::class);

        $this->listener->handleSoapFault($fault);
    }

    public function testSoap12SenderFaultCodeMapsToInvalidRequestException(): void
    {
        $fault = new SoapFault('env:Sender', self::REAL_FAULT_STRING);

        $this->expectException(InvalidRequestException::class);

        $this->listener->handleSoapFault($fault);
    }

    public function testServerFaultCodeMapsToServiceUnavailableException(): void
    {
        $fault = new SoapFault('env:Server', 'TEDB-ERR-9 - Internal error');

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame('TEDB-ERR-9', $e->getErrorCode());
            $this->assertStringContainsString('Internal error in the EU VAT service', $e->getMessage());
        }
    }

    public function testSoap12ReceiverFaultCodeMapsToServiceUnavailableException(): void
    {
        $fault = new SoapFault('env:Receiver', 'Service temporarily unavailable');

        $this->expectException(ServiceUnavailableException::class);

        $this->listener->handleSoapFault($fault);
    }

    public function testUnrecognisedFaultCodeMapsToSoapFaultException(): void
    {
        $fault = new SoapFault('env:VersionMismatch', 'Unsupported SOAP version');

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected SoapFaultException');
        } catch (SoapFaultException $e) {
            $this->assertSame('env:VersionMismatch', $e->getFaultCode());
            $this->assertSame('Unsupported SOAP version', $e->getFaultString());
            $this->assertStringContainsString(
                'SOAP fault occurred (env:VersionMismatch): Unsupported SOAP version',
                $e->getMessage()
            );
        }
    }

    /**
     * A TEDB identifier alone must not be treated as a classification signal
     *
     * The legacy mapping keyed on fault codes such as TEDB-101, which the service
     * never sends. Such a code carries no client/server attribution, so it has to
     * fall through to the generic SOAP fault exception.
     */
    public function testLegacyTedbFaultCodeIsNotClassified(): void
    {
        $fault = new SoapFault('TEDB-101', 'Invalid country code');

        $this->expectException(SoapFaultException::class);
        $this->expectExceptionMessage('SOAP fault occurred (TEDB-101): Invalid country code');

        $this->listener->handleSoapFault($fault);
    }

    public function testFaultStringWithoutTedbCodeFallsBackToFaultCode(): void
    {
        $fault = new SoapFault('env:Client', 'Request is not valid');

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame('env:Client', $e->getErrorCode());
            $this->assertStringContainsString(
                'Invalid request rejected by the EU VAT service (env:Client): Request is not valid',
                $e->getMessage()
            );
        }
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
        $fault = new SoapFault('env:Client', '');
        // Unset faultstring to test fallback
        unset($fault->faultstring);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage(
            'Invalid request rejected by the EU VAT service (env:Client): No fault string provided'
        );

        $this->listener->handleSoapFault($fault);
    }

    /**
     * An empty fault string must not leave the message ending in a dangling colon
     */
    public function testHandleFaultWithEmptyFaultStringUsesDefault(): void
    {
        $fault = new SoapFault('env:Client', '');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage(
            'Invalid request rejected by the EU VAT service (env:Client): No fault string provided'
        );

        $this->listener->handleSoapFault($fault);
    }

    /**
     * A whitespace-only fault string is as unusable as an empty one
     */
    public function testHandleFaultWithBlankFaultStringUsesDefault(): void
    {
        $fault = new SoapFault('env:Client', "  \n ");

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage(
            'Invalid request rejected by the EU VAT service (env:Client): No fault string provided'
        );

        $this->listener->handleSoapFault($fault);
    }

    /**
     * A TEDB identifier mentioned mid-sentence is not the service's error code
     *
     * The service always leads with the identifier; anything else is prose and must
     * not be mistaken for a code consumers can branch on.
     */
    public function testTedbIdentifierNotAtStartIsNotUsedAsErrorCode(): void
    {
        $fault = new SoapFault('env:Client', 'no code here but mentions TEDB-ERR-77 inside');

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame('env:Client', $e->getErrorCode());
        }
    }

    public function testHandleFaultWithDetailLogsDetail(): void
    {
        $fault = $this->createRealClientFault();
        $detail = $fault->detail;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'SOAP Fault received from EU VAT service',
                $this->callback(fn($context): bool => $context['fault_detail'] === $detail)
            );

        $this->expectException(InvalidRequestException::class);

        $this->listener->handleSoapFault($fault);
    }

    /**
     * An unexpected detail shape must not break fault mapping
     */
    public function testUnparsableDetailIsIgnoredInMessage(): void
    {
        $fault = new SoapFault('env:Client', self::REAL_FAULT_STRING);
        $fault->detail = 'a plain string the service never sends';

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame(
                'Invalid request rejected by the EU VAT service (TEDB-ERR-2): ' . self::REAL_FAULT_STRING,
                $e->getMessage()
            );
        }
    }

    /**
     * A self-referencing detail must not send the collector into infinite recursion
     */
    public function testRecursiveDetailIsBounded(): void
    {
        $node = new stdClass();
        $node->child = $node;

        $fault = new SoapFault('env:Client', self::REAL_FAULT_STRING);
        $fault->detail = $node;

        $this->expectException(InvalidRequestException::class);

        $this->listener->handleSoapFault($fault);
    }

    public function testHandleFaultPreservesOriginalFaultAsPrevious(): void
    {
        $fault = $this->createRealClientFault();

        try {
            $this->listener->handleSoapFault($fault);
            $this->fail('Expected exception was not thrown');
        } catch (InvalidRequestException $e) {
            $this->assertSame($fault, $e->getPrevious());
        }
    }

    public function testIsClientValidationErrorIdentifiesClientErrors(): void
    {
        $this->assertTrue($this->listener->isClientValidationError('env:Client'));
        $this->assertTrue($this->listener->isClientValidationError('Client'));
        $this->assertTrue($this->listener->isClientValidationError('soap:Sender'));
        $this->assertFalse($this->listener->isClientValidationError('env:Server'));
        $this->assertFalse($this->listener->isClientValidationError('TEDB-101'));
        $this->assertFalse($this->listener->isClientValidationError('UNKNOWN'));
    }

    public function testIsServerErrorIdentifiesServerErrors(): void
    {
        $this->assertTrue($this->listener->isServerError('env:Server'));
        $this->assertTrue($this->listener->isServerError('Server'));
        $this->assertTrue($this->listener->isServerError('soap:Receiver'));
        $this->assertFalse($this->listener->isServerError('env:Client'));
        $this->assertFalse($this->listener->isServerError('TEDB-400'));
        $this->assertFalse($this->listener->isServerError('UNKNOWN'));
    }
}
