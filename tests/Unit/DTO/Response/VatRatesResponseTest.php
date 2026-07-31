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
            new VatRateResult('DE', new VatRate('STANDARD', '19.0'), $date),
            new VatRateResult('DE', new VatRate('REDUCED', '7.0'), $date, null, 'FOODSTUFFS', 'Foodstuffs'),
            new VatRateResult('FR', new VatRate('STANDARD', '20.0'), $date),
            new VatRateResult('FR', new VatRate('REDUCED', '5.5'), $date, null, 'FOODSTUFFS', 'Foodstuffs'),
            new VatRateResult('IT', new VatRate('STANDARD', '22.0'), $date),
            new VatRateResult('IT', new VatRate('REDUCED', '10.0'), $date, null, 'ACCOMMODATION', 'Accommodation'),
        ];

        $this->response = new VatRatesResponse($results);
    }

    public function testGetResults(): void
    {
        $results = $this->response->getResults();

        $this->assertCount(6, $results);
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
    }

    public function testGetResultsByCategory(): void
    {
        $foodstuffResults = $this->response->getResultsByCategory('FOODSTUFFS');

        $this->assertCount(2, $foodstuffResults);
        $this->assertEquals('FOODSTUFFS', $foodstuffResults[0]->getCategory());
        $this->assertEquals('FOODSTUFFS', $foodstuffResults[1]->getCategory());
        $this->assertEquals(['DE', 'FR'], array_map(
            static fn(VatRateResult $result): string => $result->getMemberState(),
            $foodstuffResults
        ));
    }

    /**
     * A category filter must not sweep in results carrying a different category,
     * nor the standard rates that carry no category at all.
     */
    public function testGetResultsByCategoryExcludesOtherAndUncategorisedResults(): void
    {
        $accommodationResults = $this->response->getResultsByCategory('ACCOMMODATION');

        $this->assertCount(1, $accommodationResults);
        $this->assertEquals('IT', $accommodationResults[0]->getMemberState());
        $this->assertEquals('ACCOMMODATION', $accommodationResults[0]->getCategory());
    }

    public function testGetResultsByNonExistentCategory(): void
    {
        $results = $this->response->getResultsByCategory('NONEXISTENT');

        $this->assertCount(0, $results);
    }

    public function testIteratorInterface(): void
    {
        $count = 0;
        foreach ($this->response as $key => $result) {
            $this->assertSame($count, $key, 'Iterator keys must be sequential');
            $this->assertInstanceOf(VatRateResult::class, $result);
            $count++;
        }

        $this->assertEquals(6, $count);
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

        $this->response[0] = new VatRateResult('XX', new VatRate('STANDARD', '0.0'), new DateTime());
    }

    public function testArrayUnsetIsImmutable(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('VatRatesResponse is immutable');

        unset($this->response[0]);
    }

    public function testCountInterface(): void
    {
        $this->assertCount(6, $this->response);
        $this->assertEquals(6, count($this->response));
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
