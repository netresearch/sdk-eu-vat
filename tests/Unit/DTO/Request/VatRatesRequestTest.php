<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\DTO\Request;

use DateTime;
use DateTimeImmutable;
use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test VatRatesRequest DTO
 */
class VatRatesRequestTest extends TestCase
{
    public function testValidRequest(): void
    {
        $memberStates = ['DE', 'FR', 'IT'];
        $date = new DateTime('2024-01-01');

        $request = new VatRatesRequest($memberStates, $date);

        $this->assertEquals($memberStates, $request->getMemberStates());
        $this->assertSame($date, $request->getSituationOn());
    }

    public function testNormalizesLowercaseCountryCodes(): void
    {
        $request = new VatRatesRequest(['de', 'fr'], new DateTime());

        $this->assertEquals(['DE', 'FR'], $request->getMemberStates());
    }

    public function testRemovesDuplicateCountryCodes(): void
    {
        $request = new VatRatesRequest(['DE', 'FR', 'DE', 'IT', 'FR'], new DateTime());

        $this->assertEquals(['DE', 'FR', 'IT'], $request->getMemberStates());
    }

    public function testTrimsWhitespace(): void
    {
        $request = new VatRatesRequest([' DE ', 'FR  ', '  IT'], new DateTime());

        $this->assertEquals(['DE', 'FR', 'IT'], $request->getMemberStates());
    }

    public function testThrowsOnEmptyMemberStates(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Member states array cannot be empty');

        new VatRatesRequest([], new DateTime());
    }

    public function testThrowsOnInvalidCountryCodeLength(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid member state code length: DEU');

        new VatRatesRequest(['DEU'], new DateTime());
    }

    public function testThrowsOnNonStringCountryCode(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid member state code type: expected string, got integer');

        new VatRatesRequest([123], new DateTime());
    }

    public function testThrowsOnInvalidCharacters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid member state code format: D1');

        new VatRatesRequest(['D1'], new DateTime());
    }

    public function testThrowsOnFutureDateBeyondLimit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Date cannot be more than 5 years in the future');

        new VatRatesRequest(['DE'], new DateTime('+10 years'));
    }

    public function testThrowsOnDateBeforeLimit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Date cannot be before January 1, 2000');

        new VatRatesRequest(['DE'], new DateTime('1999-12-31'));
    }

    public function testAcceptsValidFutureDate(): void
    {
        $date = new DateTime('+2 years');
        $request = new VatRatesRequest(['DE'], $date);

        $this->assertSame($date, $request->getSituationOn());
    }

    public function testAcceptsDateTimeImmutable(): void
    {
        $date = new DateTimeImmutable('2024-01-01');
        $request = new VatRatesRequest(['DE'], $date);

        $this->assertSame($date, $request->getSituationOn());
    }
}
