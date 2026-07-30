<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\DTO\Response;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Netresearch\EuVatSdk\Exception\ParseException;

/**
 * Value object representing a VAT rate
 *
 * This class encapsulates VAT rate information with precise decimal handling
 * using BigDecimal for financial calculations. It provides helper methods
 * to identify different VAT rate types.
 *
 * @example Creating a VAT rate:
 * ```php
 * $rate = new VatRate('STANDARD', '19.0');
 * echo $rate->getValue(); // BigDecimal("19.0")
 * echo $rate->getRawValue(); // "19.0"
 * echo $rate->getType(); // "STANDARD"
 * echo $rate->isStandard(); // true
 * ```
 *
 * @example Using BigDecimal for calculations:
 * ```php
 * $rate = new VatRate('STANDARD', '19.0');
 * $amount = BigDecimal::of('100.00');
 * $vatAmount = $amount->multipliedBy($rate->getValue())->dividedBy(100, 2);
 * echo $vatAmount; // "19.00"
 * ```
 *
 * @example Exempt rate without a value (the service omits the value element):
 * ```php
 * $rate = new VatRate('EXEMPTED', null);
 * echo $rate->isExempt(); // true
 * var_dump($rate->getValue()); // NULL
 * ```
 *
 * A rate carries no category. The service reports the category on the enclosing
 * result element (`vatRateResults/category` in `VatRetrievalServiceType.xsd`), a
 * sibling of `rate`, so it is exposed as VatRateResult::getCategory():
 * ```php
 * $result = $response->getResultsByCategory('FOODSTUFFS')[0];
 * echo $result->getCategory();            // "FOODSTUFFS"
 * echo $result->getRate()->isReduced();   // true
 * ```
 *
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRate implements \Stringable
{
    private ?BigDecimal $decimalValue = null;

    /**
     * @param string      $type     VAT rate type (e.g., 'STANDARD', 'REDUCED', 'REDUCED[1]').
     * @param string|null $value    Percentage value as string (e.g., "19.0"), or null when the
     *                              service provides no value. The XSD declares the value element
     *                              with minOccurs="0" for every rate type, so any type may arrive
     *                              without a value — typically exempt/out-of-scope rates, but also
     *                              rates a member state does not levy (e.g. PARKING_RATE).
     */
    public function __construct(
        private readonly string $type,
        private readonly ?string $value
    ) {
    }


    /**
     * Get the VAT rate type
     *
     * @return string The rate type (e.g., 'STANDARD', 'REDUCED')
     */
    public function getType(): string
    {
        return strtoupper(trim($this->type));
    }

    /**
     * Get the VAT rate as a BigDecimal for precise calculations
     *
     * Returns null when the service provided no value for this rate. The XSD marks
     * the value element optional for every rate type, so callers must handle null
     * for any type, not only exempt/out-of-scope ones (see isExempt()).
     *
     * @return BigDecimal|null The VAT rate as a BigDecimal instance, or null if no value was provided
     * @throws ParseException If the value cannot be parsed as a decimal
     */
    public function getValue(): ?BigDecimal
    {
        if ($this->value === null) {
            return null;
        }

        if (!$this->decimalValue instanceof BigDecimal) {
            try {
                $this->decimalValue = BigDecimal::of($this->value);
            } catch (MathException $e) {
                throw new ParseException(
                    sprintf('Failed to parse decimal value: %s', $this->value),
                    0,
                    $e
                );
            }
        }

        return $this->decimalValue;
    }

    /**
     * Get the value as a BigDecimal for precise calculations
     *
     * @deprecated Use getValue() instead
     * @return BigDecimal|null The VAT rate as a BigDecimal instance, or null if no value was provided
     */
    public function getDecimalValue(): ?BigDecimal
    {
        return $this->getValue();
    }

    /**
     * Get the raw string value as received from the API
     *
     * @return string|null The VAT rate percentage as a string (e.g., "19.0"),
     *                     or null if the service provided no value for this rate
     */
    public function getRawValue(): ?string
    {
        return $this->value;
    }

    /**
     * Get the value as float (use with caution for calculations)
     *
     * @deprecated Since 1.0.0, use getValue() for precise calculations
     * @return float|null The VAT rate as a floating-point number, or null if no value was provided
     */
    public function getValueAsFloat(): ?float
    {
        return $this->getValue()?->toFloat();
    }

    /**
     * Check if this is a standard VAT rate
     *
     * @return boolean True if the rate type is 'STANDARD' or 'DEFAULT'
     */
    public function isStandard(): bool
    {
        $normalizedType = $this->getType();
        return $normalizedType === 'STANDARD' || $normalizedType === 'DEFAULT';
    }

    /**
     * Check if this is a reduced VAT rate
     *
     * @return boolean True if the rate type starts with 'REDUCED' or is 'REDUCED_RATE'
     */
    public function isReduced(): bool
    {
        $normalizedType = $this->getType();
        return str_starts_with($normalizedType, 'REDUCED') || $normalizedType === 'REDUCED_RATE';
    }

    /**
     * Check if this is a super-reduced VAT rate
     *
     * @return boolean True if the rate type is 'SUPER_REDUCED' or 'SUPER_REDUCED_RATE'
     */
    public function isSuperReduced(): bool
    {
        $normalizedType = $this->getType();
        return $normalizedType === 'SUPER_REDUCED' || $normalizedType === 'SUPER_REDUCED_RATE';
    }

    /**
     * Check if this is a parking rate
     *
     * @return boolean True if the rate type is 'PK', 'PARKING', or 'PARKING_RATE'
     */
    public function isParkingRate(): bool
    {
        $normalizedType = $this->getType();
        return $normalizedType === 'PK' || $normalizedType === 'PARKING' || $normalizedType === 'PARKING_RATE';
    }

    /**
     * Check if this is a zero rate
     *
     * @return boolean True if the rate type is 'Z' or 'ZERO'
     */
    public function isZeroRate(): bool
    {
        $normalizedType = $this->getType();
        return $normalizedType === 'Z' || $normalizedType === 'ZERO';
    }

    /**
     * Check if this is an exempt rate
     *
     * @return boolean True if the rate type is 'E', 'EXEMPT', 'EXEMPTED', 'NOT_APPLICABLE', or 'OUT_OF_SCOPE'
     */
    public function isExempt(): bool
    {
        $normalizedType = $this->getType();
        return $normalizedType === 'E' || $normalizedType === 'EXEMPT' || $normalizedType === 'EXEMPTED' ||
               $normalizedType === 'NOT_APPLICABLE' || $normalizedType === 'OUT_OF_SCOPE';
    }

    /**
     * String representation of the VAT rate
     *
     * @return string The VAT rate percentage as a string, or an empty string if no value was provided
     */
    public function __toString(): string
    {
        return $this->getRawValue() ?? '';
    }
}
