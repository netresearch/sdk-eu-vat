<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\DTO\Response;

use DateTime;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use PHPUnit\Framework\TestCase;

/**
 * Test VatRateResult DTO
 */
class VatRateResultTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $rate = new VatRate('STANDARD', '19.0');
        $date = new DateTime('2024-01-01');
        $result = new VatRateResult('DE', 'STANDARD', $rate, $date, 'Test comment');

        $this->assertEquals('DE', $result->getMemberState());
        $this->assertEquals('STANDARD', $result->getType());
        $this->assertSame($rate, $result->getRate());
        $this->assertSame($date, $result->getSituationOn());
        $this->assertEquals('Test comment', $result->getComment());
    }

    public function testMemberStateNormalization(): void
    {
        $rate = new VatRate('STANDARD', '19.0');
        $date = new DateTime('2024-01-01');

        // Test lowercase normalization
        $result = new VatRateResult('de', 'STANDARD', $rate, $date);
        $this->assertEquals('DE', $result->getMemberState());

        // Test whitespace trimming
        $result = new VatRateResult('  fr  ', 'STANDARD', $rate, $date);
        $this->assertEquals('FR', $result->getMemberState());

        // Test mixed case
        $result = new VatRateResult('It', 'STANDARD', $rate, $date);
        $this->assertEquals('IT', $result->getMemberState());
    }

    public function testOptionalComment(): void
    {
        $rate = new VatRate('STANDARD', '19.0');
        $date = new DateTime('2024-01-01');

        // Without comment
        $result = new VatRateResult('DE', 'STANDARD', $rate, $date);
        $this->assertNull($result->getComment());

        // With comment
        $result = new VatRateResult('DE', 'STANDARD', $rate, $date, 'Special rate');
        $this->assertEquals('Special rate', $result->getComment());
    }
}
