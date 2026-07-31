<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\DTO\Response;

use ArrayAccess;
use Countable;
use Iterator;
use LogicException;

/**
 * Response DTO containing VAT rate results for multiple member states
 *
 * This class represents the complete response from the EU VAT service containing
 * VAT rate information for the requested member states. It provides convenient
 * methods for filtering and accessing the results.
 *
 * Note that the service returns one result per rate type (STANDARD, REDUCED, ...) per
 * member state, and that VatRate::getValue() is null for rates the service reports
 * without a percentage. Both are handled explicitly in the examples below.
 *
 * @example Basic usage:
 * ```php
 * $response = $client->retrieveVatRates($request);
 *
 * // Iterate over all results
 * foreach ($response->getResults() as $result) {
 *     $value = $result->getRate()->getValue();
 *     echo sprintf(
 *         "%s: %s (%s)\n",
 *         $result->getMemberState(),
 *         $value === null ? 'n/a' : $value . '%',
 *         $result->getRate()->getType()
 *     );
 * }
 * ```
 *
 * @example Picking the standard rate for one country:
 * ```php
 * foreach ($response->getResultsForCountry('DE') as $result) {
 *     if (!$result->getRate()->isStandard()) {
 *         continue;
 *     }
 *
 *     $value = $result->getRate()->getValue();
 *     if ($value !== null) {
 *         echo 'DE standard rate: ' . $value . "%\n";
 *     }
 * }
 * ```
 *
 * @example Filtering by category:
 * ```php
 * // The service reports a category per result, and omits it for standard rates,
 * // so this returns only the reduced-rate results that name FOODSTUFFS.
 * $foodstuffRates = $response->getResultsByCategory('FOODSTUFFS');
 * foreach ($foodstuffRates as $result) {
 *     $value = $result->getRate()->getValue();
 *     if ($value !== null) {
 *         echo $result->getMemberState() . ': ' . $value . "% (" . $result->getCategory() . ")\n";
 *     }
 * }
 * ```
 *
 * @implements Iterator<int, VatRateResult>
 * @implements ArrayAccess<int, VatRateResult>
 *
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class VatRatesResponse implements Iterator, ArrayAccess, Countable
{
    /**
     * @var array<VatRateResult> Array of VAT rate results
     */
    private readonly array $results;

    /**
     * @var int Current position for Iterator interface
     */
    private int $position = 0;

    /**
     * @param array<VatRateResult> $results Array of VAT rate results
     */
    public function __construct(array $results)
    {
        // Ensure array is indexed numerically
        $this->results = array_values($results);
    }

    /**
     * Get all VAT rate results
     *
     * @return array<VatRateResult> All results from the response
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get results filtered by country
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return array<VatRateResult> Results for the specified country
     */
    public function getResultsForCountry(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        return $this->filterResults(
            fn(VatRateResult $result): bool => $result->getMemberState() === $countryCode
        );
    }

    /**
     * Get results filtered by category
     *
     * Matches on the category identifier reported by the service (see
     * VatRateResult::getCategory()). Results the service reported without a category —
     * standard rates, for instance — never match. The comparison is exact, because the
     * identifiers are a fixed vocabulary defined in the TEDB External Interface
     * Specification.
     *
     * @param string $category Category identifier (e.g., 'FOODSTUFFS')
     * @return array<VatRateResult> Results with the specified category, empty if none match
     */
    public function getResultsByCategory(string $category): array
    {
        return $this->filterResults(
            fn(VatRateResult $result): bool => $result->getCategory() === $category
        );
    }

    /**
     * Filter results using a callback function
     *
     * @param callable(VatRateResult): bool $callback Filter callback
     * @return array<VatRateResult> Filtered results
     */
    private function filterResults(callable $callback): array
    {
        return array_values(array_filter($this->results, $callback));
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

    /** @SuppressWarnings("PHPMD.UnusedFormalParameter") */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('VatRatesResponse is immutable');
    }

    /** @SuppressWarnings("PHPMD.UnusedFormalParameter") */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('VatRatesResponse is immutable');
    }

    // Countable interface implementation

    public function count(): int
    {
        return count($this->results);
    }
}
