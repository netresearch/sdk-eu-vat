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
 * @example With category information:
 * ```php
 * $rate = new VatRate('REDUCED', '7.0', 'FOODSTUFFS');
 * echo $rate->getCategory(); // "FOODSTUFFS"
 * echo $rate->isReduced(); // true
 * ```
 *
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRate implements \Stringable
{
    private readonly string $type;
    private ?BigDecimal $decimalValue = null;

    /**
     * @param string      $type     VAT rate type (e.g., 'STANDARD', 'REDUCED', 'REDUCED[1]').
     * @param string      $value    Percentage value as string (e.g., "19.0").
     * @param string|null $category Optional category identifier (e.g., 'FOODSTUFFS').
     * @throws ParseException If the value cannot be parsed as a decimal.
     */
    public function __construct(
        string $type,
        private readonly string $value,
        private readonly ?string $category = null
    ) {
        $this->type = strtoupper(trim($type));
        // Initialize decimal value immediately for constructor calls
        $this->decimalValue = $this->initializeDecimalValue();
    }

    /**
     * Initialize the decimal value from the string value
     *
     * @throws ParseException If the value cannot be parsed as a decimal
     */
    private function initializeDecimalValue(): BigDecimal
    {
        try {
            return BigDecimal::of($this->value);
        } catch (MathException $e) {
            throw new ParseException(
                sprintf('Failed to parse decimal value: %s', $this->value),
                0,
                $e
            );
        }
    }

    /**
     * Get the VAT rate type
     *
     * @return string The rate type (e.g., 'STANDARD', 'REDUCED')
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the VAT rate as a BigDecimal for precise calculations
     *
     * @return BigDecimal The VAT rate as a BigDecimal instance
     */
    public function getValue(): BigDecimal
    {
        // Lazy initialization for SOAP ClassMap compatibility
        if (!$this->decimalValue instanceof BigDecimal) {
            $this->decimalValue = $this->initializeDecimalValue();
        }
        return $this->decimalValue;
    }

    /**
     * Get the value as a BigDecimal for precise calculations
     *
     * @deprecated Use getValue() instead
     * @return BigDecimal The VAT rate as a BigDecimal instance
     */
    public function getDecimalValue(): BigDecimal
    {
        return $this->getValue();
    }

    /**
     * Get the raw string value as received from the API
     *
     * @return string The VAT rate percentage as a string (e.g., "19.0")
     */
    public function getRawValue(): string
    {
        return $this->value;
    }

    /**
     * Get the value as float (use with caution for calculations)
     *
     * @deprecated Since 1.0.0, use getValue() for precise calculations
     * @return float The VAT rate as a floating-point number
     */
    public function getValueAsFloat(): float
    {
        return $this->getValue()->toFloat();
    }

    /**
     * Get the category if available
     *
     * @return string|null The category identifier or null if not specified
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }


    /**
     * Check if this is a standard VAT rate
     *
     * @return boolean True if the rate type is 'STANDARD' or 'DEFAULT'
     */
    public function isStandard(): bool
    {
        return $this->type === 'STANDARD' || $this->type === 'DEFAULT';
    }

    /**
     * Check if this is a reduced VAT rate
     *
     * @return boolean True if the rate type starts with 'REDUCED' or is 'REDUCED_RATE'
     */
    public function isReduced(): bool
    {
        return str_starts_with($this->type, 'REDUCED') || $this->type === 'REDUCED_RATE';
    }

    /**
     * Check if this is a super-reduced VAT rate
     *
     * @return boolean True if the rate type is 'SUPER_REDUCED'
     */
    public function isSuperReduced(): bool
    {
        return $this->type === 'SUPER_REDUCED';
    }

    /**
     * Check if this is a parking rate
     *
     * @return boolean True if the rate type is 'PK' or 'PARKING'
     */
    public function isParkingRate(): bool
    {
        return $this->type === 'PK' || $this->type === 'PARKING';
    }

    /**
     * Check if this is a zero rate
     *
     * @return boolean True if the rate type is 'Z' or 'ZERO'
     */
    public function isZeroRate(): bool
    {
        return $this->type === 'Z' || $this->type === 'ZERO';
    }

    /**
     * Check if this is an exempt rate
     *
     * @return boolean True if the rate type is 'E' or 'EXEMPT'
     */
    public function isExempt(): bool
    {
        return $this->type === 'E' || $this->type === 'EXEMPT';
    }

    /**
     * String representation of the VAT rate
     *
     * @return string The VAT rate percentage as a string
     */
    public function __toString(): string
    {
        return $this->getRawValue();
    }
}
