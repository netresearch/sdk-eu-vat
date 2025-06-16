<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\TypeConverter;

use DateTimeImmutable;
use DateTimeInterface;
use Netresearch\EuVatSdk\Exception\ParseException;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;
use Throwable;

/**
 * Type converter for xsd:date (date without time component)
 *
 * This converter handles automatic conversion between XML date strings (YYYY-MM-DD format)
 * and PHP DateTimeImmutable objects. It is specifically designed for xsd:date which
 * does NOT include time information.
 *
 * CRITICAL: This converter uses 'Y-m-d' format (date only) as required by the EU VAT service.
 * Using 'Y-m-d\TH:i:s' format will cause immediate service failures.
 *
 * @example XML to PHP conversion:
 * ```php
 * $converter = new DateTypeConverter();
 * $date = $converter->convertXmlToPhp('2024-01-15');
 * // Returns: DateTimeImmutable object for 2024-01-15
 * ```
 *
 * @example PHP to XML conversion:
 * ```php
 * $converter = new DateTypeConverter();
 * $xmlDate = $converter->convertPhpToXml(new DateTime('2024-01-15 14:30:00'));
 * // Returns: "2024-01-15" (time component stripped)
 * ```
 *
 * @package Netresearch\EuVatSdk\TypeConverter
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class DateTypeConverter implements TypeConverterInterface
{
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
     * @return string Always returns 'date' for xsd:date
     */
    public function getTypeName(): string
    {
        return 'date';
    }

    /**
     * Convert XML date string to PHP DateTimeImmutable
     *
     * @param string $data XML date string in YYYY-MM-DD format
     * @return DateTimeImmutable PHP date object (time set to 00:00:00)
     * @throws ParseException If the date string cannot be parsed
     *
     * @example
     * ```php
     * $date = $converter->convertXmlToPhp('2024-01-15');
     * echo $date->format('Y-m-d'); // "2024-01-15"
     * ```
     */
    public function convertXmlToPhp(string $data): DateTimeImmutable
    {
        try {
            // Parse the date string - DateTimeImmutable will handle various formats
            $date = new DateTimeImmutable($data);

            // Ensure we only have the date part (strip any time component)
            return $date->setTime(0, 0, 0);
        } catch (Throwable $e) {
            throw new ParseException(
                sprintf('Failed to parse date value: %s (expected format: YYYY-MM-DD)', $data),
                0,
                $e
            );
        }
    }

    /**
     * Convert PHP DateTimeInterface to XML date string
     *
     * CRITICAL: Returns ONLY the date part in 'Y-m-d' format.
     * Time components are intentionally stripped as required by xsd:date.
     *
     * @param mixed $data DateTimeInterface or date string
     * @return string XML date string in YYYY-MM-DD format
     * @throws ParseException If the input cannot be converted to a date
     *
     * @example
     * ```php
     * $xmlDate = $converter->convertPhpToXml(new DateTime('2024-01-15 14:30:00'));
     * echo $xmlDate; // "2024-01-15"
     * ```
     */
    public function convertPhpToXml(mixed $data): string
    {
        if ($data instanceof DateTimeInterface) {
            // CRITICAL: Use 'Y-m-d' format only (no time component)
            return $data->format('Y-m-d');
        }

        if (is_string($data)) {
            try {
                $date = new DateTimeImmutable($data);
                return $date->format('Y-m-d');
            } catch (Throwable $e) {
                throw new ParseException(
                    sprintf('Failed to parse date string: %s', $data),
                    0,
                    $e
                );
            }
        }

        throw new ParseException(
            sprintf(
                'Cannot convert %s to XML date. Expected DateTimeInterface or string, got: %s',
                is_object($data) ? get_class($data) : gettype($data),
                is_scalar($data) ? (string) $data : 'non-scalar'
            )
        );
    }
}
