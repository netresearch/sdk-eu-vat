<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Util;

use Netresearch\EuVatSdk\Util\CorrelationIdProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test correlation ID provider functionality
 *
 * @package Netresearch\EuVatSdk\Tests\Unit\Util
 */
class CorrelationIdProviderTest extends TestCase
{
    private CorrelationIdProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new CorrelationIdProvider();
    }

    public function testGenerateReturnsValidUuid(): void
    {
        $uuid = $this->provider->generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
            'Generated UUID should be valid v4 format'
        );
    }

    public function testGenerateReturnsUniqueIds(): void
    {
        $id1 = $this->provider->generate();
        $id2 = $this->provider->generate();

        $this->assertNotEquals($id1, $id2, 'Generated IDs should be unique');
    }

    public function testProvideReturnsProvidedIdWhenGiven(): void
    {
        $providedId = 'custom-correlation-id';

        $result = $this->provider->provide($providedId);

        $this->assertEquals($providedId, $result);
    }

    public function testProvideIgnoresEmptyProvidedId(): void
    {
        $result = $this->provider->provide('');

        // Should generate a new UUID instead of returning empty string
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    public function testProvideExtractsFromHeaders(): void
    {
        $headers = [
            'X-Request-ID' => 'header-correlation-id',
            'Content-Type' => 'application/json',
        ];

        $result = $this->provider->provide(null, $headers);

        $this->assertEquals('header-correlation-id', $result);
    }

    public function testProvideHandlesCaseInsensitiveHeaders(): void
    {
        $headers = [
            'x-request-id' => 'lowercase-header-id',
            'Content-Type' => 'application/json',
        ];

        $result = $this->provider->provide(null, $headers);

        $this->assertEquals('lowercase-header-id', $result);
    }

    public function testProvideChecksMultipleHeaderTypes(): void
    {
        $headers = [
            'X-Correlation-ID' => 'correlation-header-id',
        ];

        $result = $this->provider->provide(null, $headers);

        $this->assertEquals('correlation-header-id', $result);
    }

    public function testProvideTrimsHeaderValues(): void
    {
        $headers = [
            'X-Request-ID' => '  whitespace-id  ',
        ];

        $result = $this->provider->provide(null, $headers);

        $this->assertEquals('whitespace-id', $result);
    }

    public function testProvideGeneratesNewIdWhenNoHeadersOrProvidedId(): void
    {
        $result = $this->provider->provide();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    public function testProvideIgnoresEmptyHeaderValues(): void
    {
        $headers = [
            'X-Request-ID' => '',
            'X-Correlation-ID' => 'valid-correlation-id',
        ];

        $result = $this->provider->provide(null, $headers);

        $this->assertEquals('valid-correlation-id', $result);
    }
}
