<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\DTO\Response;

use DateTime;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use PHPUnit\Framework\TestCase;

/**
 * Test VatRatesResponse DTO
 */
class VatRatesResponseTest extends TestCase
{
    private VatRatesResponse $response;

    protected function setUp(): void
    {
        $date = new DateTime('2024-01-01');

        $results = [
            new VatRateResult('DE', 'STANDARD', new VatRate('STANDARD', '19.0'), $date),
            new VatRateResult('DE', 'REDUCED', new VatRate('REDUCED', '7.0', 'FOODSTUFFS'), $date),
            new VatRateResult('FR', 'STANDARD', new VatRate('STANDARD', '20.0'), $date),
            new VatRateResult('FR', 'REDUCED', new VatRate('REDUCED', '5.5', 'FOODSTUFFS'), $date),
            new VatRateResult('IT', 'STANDARD', new VatRate('STANDARD', '22.0'), $date),
        ];

        $this->response = new VatRatesResponse($results);
    }

    public function testGetResults(): void
    {
        $results = $this->response->getResults();

        $this->assertCount(5, $results);
        $this->assertInstanceOf(VatRateResult::class, $results[0]);
    }

    public function testGetResultsForCountry(): void
    {
        $germanyResults = $this->response->getResultsForCountry('DE');

        $this->assertCount(2, $germanyResults);
        $this->assertEquals('DE', $germanyResults[0]->getMemberState());
        $this->assertEquals('DE', $germanyResults[1]->getMemberState());
    }

    public function testGetResultsForCountryIsCaseInsensitive(): void
    {
        $germanyResults = $this->response->getResultsForCountry('de');

        $this->assertCount(2, $germanyResults);
    }

    public function testGetResultsForNonExistentCountry(): void
    {
        $results = $this->response->getResultsForCountry('XX');

        $this->assertCount(0, $results);
        $this->assertIsArray($results);
    }

    public function testGetResultsByCategory(): void
    {
        $foodstuffResults = $this->response->getResultsByCategory('FOODSTUFFS');

        $this->assertCount(2, $foodstuffResults);
        $this->assertEquals('FOODSTUFFS', $foodstuffResults[0]->getRate()->getCategory());
        $this->assertEquals('FOODSTUFFS', $foodstuffResults[1]->getRate()->getCategory());
    }

    public function testGetResultsByNonExistentCategory(): void
    {
        $results = $this->response->getResultsByCategory('NONEXISTENT');

        $this->assertCount(0, $results);
        $this->assertIsArray($results);
    }

    public function testIteratorInterface(): void
    {
        $count = 0;
        foreach ($this->response as $key => $result) {
            $this->assertIsInt($key);
            $this->assertInstanceOf(VatRateResult::class, $result);
            $count++;
        }

        $this->assertEquals(5, $count);
    }

    public function testArrayAccessInterface(): void
    {
        $this->assertTrue(isset($this->response[0]));
        $this->assertFalse(isset($this->response[10]));

        $firstResult = $this->response[0];
        $this->assertInstanceOf(VatRateResult::class, $firstResult);
        $this->assertEquals('DE', $firstResult->getMemberState());
    }

    public function testArrayAccessIsImmutable(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('VatRatesResponse is immutable');

        $this->response[0] = new VatRateResult('XX', 'STANDARD', new VatRate('STANDARD', '0.0'), new DateTime());
    }

    public function testArrayUnsetIsImmutable(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('VatRatesResponse is immutable');

        unset($this->response[0]);
    }

    public function testCountInterface(): void
    {
        $this->assertCount(5, $this->response);
        $this->assertEquals(5, count($this->response));
    }

    public function testEmptyResponse(): void
    {
        $emptyResponse = new VatRatesResponse([]);

        $this->assertCount(0, $emptyResponse);
        $this->assertEquals([], $emptyResponse->getResults());
        $this->assertEquals([], $emptyResponse->getResultsForCountry('DE'));
        $this->assertEquals([], $emptyResponse->getResultsByCategory('FOODSTUFFS'));
    }
}
