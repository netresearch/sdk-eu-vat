<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\DTO\Response;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * Response DTO containing VAT rate results for multiple member states
 * 
 * This class represents the complete response from the EU VAT service containing
 * VAT rate information for the requested member states. It provides convenient
 * methods for filtering and accessing the results.
 * 
 * @example Basic usage:
 * ```php
 * $response = $client->retrieveVatRates($request);
 * 
 * // Iterate over all results
 * foreach ($response->getResults() as $result) {
 *     echo sprintf(
 *         "%s: %s%% (%s)\n",
 *         $result->getMemberState(),
 *         $result->getRate()->getValue(),
 *         $result->getRate()->getType()
 *     );
 * }
 * ```
 * 
 * @example Filtering by country:
 * ```php
 * $germanyRates = $response->getResultsForCountry('DE');
 * foreach ($germanyRates as $result) {
 *     echo $result->getRate()->getType() . ': ' . $result->getRate()->getValue() . "%\n";
 * }
 * ```
 * 
 * @example Filtering by category:
 * ```php
 * $foodstuffRates = $response->getResultsByCategory('FOODSTUFFS');
 * foreach ($foodstuffRates as $result) {
 *     echo $result->getMemberState() . ': ' . $result->getRate()->getValue() . "%\n";
 * }
 * ```
 * 
 * @implements Iterator<int, VatRateResult>
 * @implements ArrayAccess<int, VatRateResult>
 * 
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRatesResponse implements Iterator, ArrayAccess, Countable
{
    /**
     * @var VatRateResult[] Array of VAT rate results
     */
    private readonly array $results;
    
    /**
     * @var int Current position for Iterator interface
     */
    private int $position = 0;
    
    /**
     * @param VatRateResult[] $results Array of VAT rate results
     */
    public function __construct(array $results)
    {
        // Ensure array is indexed numerically
        $this->results = array_values($results);
    }
    
    /**
     * Get all VAT rate results
     * 
     * @return VatRateResult[] All results from the response
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * Get results filtered by country
     * 
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return VatRateResult[] Results for the specified country
     */
    public function getResultsForCountry(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        
        return array_values(array_filter(
            $this->results,
            fn(VatRateResult $result) => $result->getMemberState() === $countryCode
        ));
    }
    
    /**
     * Get results filtered by category
     * 
     * @param string $category Category identifier (e.g., 'FOODSTUFFS')
     * @return VatRateResult[] Results with the specified category
     */
    public function getResultsByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->results,
            fn(VatRateResult $result) => $result->getRate()->getCategory() === $category
        ));
    }
    
    // Iterator interface implementation
    
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    public function current(): VatRateResult
    {
        return $this->results[$this->position];
    }
    
    public function key(): int
    {
        return $this->position;
    }
    
    public function next(): void
    {
        ++$this->position;
    }
    
    public function valid(): bool
    {
        return isset($this->results[$this->position]);
    }
    
    // ArrayAccess interface implementation
    
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->results[$offset]);
    }
    
    public function offsetGet(mixed $offset): VatRateResult
    {
        return $this->results[$offset];
    }
    
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('VatRatesResponse is immutable');
    }
    
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('VatRatesResponse is immutable');
    }
    
    // Countable interface implementation
    
    public function count(): int
    {
        return count($this->results);
    }
}