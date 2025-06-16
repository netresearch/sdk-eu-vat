<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\TypeConverter;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Netresearch\EuVatSdk\Exception\ParseException;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;

/**
 * Type converter for xsd:decimal using BigDecimal for financial precision
 *
 * This converter handles automatic conversion between XML decimal strings and
 * Brick\Math\BigDecimal objects, ensuring precise financial calculations without
 * floating-point precision issues.
 *
 * @example XML to PHP conversion:
 * ```php
 * $converter = new BigDecimalTypeConverter();
 * $decimal = $converter->convertXmlToPhp('19.75');
 * // Returns: BigDecimal object representing 19.75 exactly
 * ```
 *
 * @example PHP to XML conversion:
 * ```php
 * $converter = new BigDecimalTypeConverter();
 * $xmlDecimal = $converter->convertPhpToXml(BigDecimal::of('19.75'));
 * // Returns: "19.75"
 * ```
 *
 * @package Netresearch\EuVatSdk\TypeConverter
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class BigDecimalTypeConverter implements TypeConverterInterface
{
    /**
     * Maximum reasonable VAT rate percentage (prevents unrealistic values)
     */
    private const MAX_VAT_RATE = 100.0;

    /**
     * Minimum reasonable VAT rate percentage (allows negative for special cases)
     */
    private const MIN_VAT_RATE = -10.0;

    /**
     * Get the XML Schema namespace for this type
     *
     * @return string Always returns the W3C XML Schema namespace
     */
    public function getTypeNamespace(): string
    {
        return 'http://www.w3.org/2001/XMLSchema';
    }

    /**
     * Get the XML Schema type name this converter handles
     *
     * @return string Always returns 'decimal' for xsd:decimal
     */
    public function getTypeName(): string
    {
        return 'decimal';
    }

    /**
     * Convert XML decimal string to PHP BigDecimal
     *
     * @param string $data XML decimal string (e.g., '19.75', '0.0', '7.5')
     * @return BigDecimal PHP BigDecimal object for precise calculations
     * @throws ParseException If the decimal string cannot be parsed or is out of bounds
     *
     * @example
     * ```php
     * $decimal = $converter->convertXmlToPhp('19.75');
     * echo $decimal->toFloat(); // 19.75 (exact)
     * ```
     */
    public function convertXmlToPhp(string $data): BigDecimal
    {
        try {
            $decimal = BigDecimal::of($data);

            // Validate reasonable bounds for VAT rates
            $this->validateBounds($decimal, $data);

            return $decimal;
        } catch (MathException $e) {
            throw new ParseException(
                sprintf('Failed to parse decimal value: %s (invalid number format)', $data),
                0,
                $e
            );
        }
    }

    /**
     * Convert PHP value to XML decimal string
     *
     * @param mixed $data BigDecimal, numeric value, or numeric string
     * @return string XML decimal string with preserved precision
     * @throws ParseException If the input cannot be converted to a decimal
     *
     * @example
     * ```php
     * $xmlDecimal = $converter->convertPhpToXml(BigDecimal::of('19.75'));
     * echo $xmlDecimal; // "19.75"
     * ```
     */
    public function convertPhpToXml(mixed $data): string
    {
        if ($data instanceof BigDecimal) {
            return $data->__toString();
        }

        if (is_numeric($data)) {
            try {
                $decimal = BigDecimal::of((string) $data);
                $this->validateBounds($decimal, (string) $data);
                return $decimal->__toString();
            } catch (MathException $e) {
                throw new ParseException(
                    sprintf('Failed to convert numeric value to decimal: %s', $data),
                    0,
                    $e
                );
            }
        }

        if (is_string($data)) {
            try {
                $decimal = BigDecimal::of($data);
                $this->validateBounds($decimal, $data);
                return $decimal->__toString();
            } catch (MathException $e) {
                throw new ParseException(
                    sprintf('Failed to parse decimal string: %s', $data),
                    0,
                    $e
                );
            }
        }

        throw new ParseException(
            sprintf(
                'Cannot convert %s to XML decimal. Expected BigDecimal, numeric value, or numeric string, got: %s',
                is_object($data) ? get_class($data) : gettype($data),
                is_scalar($data) ? (string) $data : 'non-scalar'
            )
        );
    }

    /**
     * Validate that decimal values are within reasonable bounds for VAT rates
     *
     * @param BigDecimal $decimal       The decimal value to validate
     * @param string     $originalValue The original string value for error reporting
     * @throws ParseException If the value is outside reasonable bounds
     */
    private function validateBounds(BigDecimal $decimal, string $originalValue): void
    {
        $floatValue = $decimal->toFloat();

        if ($floatValue > self::MAX_VAT_RATE) {
            throw new ParseException(
                sprintf(
                    'VAT rate value too high: %s (maximum allowed: %s%%)',
                    $originalValue,
                    self::MAX_VAT_RATE
                )
            );
        }

        if ($floatValue < self::MIN_VAT_RATE) {
            throw new ParseException(
                sprintf(
                    'VAT rate value too low: %s (minimum allowed: %s%%)',
                    $originalValue,
                    self::MIN_VAT_RATE
                )
            );
        }
    }
}
