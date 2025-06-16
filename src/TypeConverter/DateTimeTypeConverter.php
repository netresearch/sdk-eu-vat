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
 * This converter handles automatic conversion between XML date strings and
 * PHP DateTimeImmutable objects. It handles date-only values and sets time
 * to start of day for consistency.
 *
 * @example XML to PHP conversion:
 * ```php
 * $converter = new DateTimeTypeConverter();
 * $dateTime = $converter->convertXmlToPhp('2024-01-15T14:30:00');
 * // Returns: DateTimeImmutable object for 2024-01-15 14:30:00
 * ```
 *
 * @example PHP to XML conversion:
 * ```php
 * $converter = new DateTimeTypeConverter();
 * $xmlDateTime = $converter->convertPhpToXml(new DateTime('2024-01-15 14:30:00'));
 * // Returns: "2024-01-15T14:30:00"
 * ```
 *
 * @package Netresearch\EuVatSdk\TypeConverter
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class DateTimeTypeConverter implements TypeConverterInterface
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
     * @param string $data XML date string (e.g., '2024-01-15')
     * @return DateTimeImmutable PHP date object with time set to start of day
     * @throws ParseException If the date string cannot be parsed
     *
     * @example
     * ```php
     * $date = $converter->convertXmlToPhp('2024-01-15');
     * echo $date->format('Y-m-d H:i:s'); // "2024-01-15 00:00:00"
     * ```
     */
    public function convertXmlToPhp(string $data): DateTimeImmutable
    {
        try {
            // Handle XML-wrapped dates from SOAP responses
            $cleanedData = $this->extractDateFromXml($data);

            // Parse date and set time to start of day for consistency
            $dateTime = new DateTimeImmutable($cleanedData);

            // Ensure time is at start of day for date-only values
            return $dateTime->setTime(0, 0, 0);
        } catch (Throwable $e) {
            throw new ParseException(
                sprintf(
                    'Failed to parse date value: %s (expected format: YYYY-MM-DD)',
                    $data
                ),
                0,
                $e
            );
        }
    }

    /**
     * Extract date value from XML-wrapped content
     *
     * @param string $data Raw data that might be XML-wrapped
     * @return string Clean date string
     */
    private function extractDateFromXml(string $data): string
    {
        // Check if data contains XML tags
        if (strpos($data, '<') !== false && strpos($data, '>') !== false) {
            // Extract content between XML tags using regex
            if (preg_match('/>([^<]+)</', $data, $matches)) {
                return trim($matches[1]);
            }

            // Fallback: try to extract date pattern from XML
            if (preg_match('/(\d{4}-\d{2}-\d{2}(?:\+\d{2}:\d{2})?)/', $data, $matches)) {
                // Remove timezone offset if present for date-only converter
                return substr($matches[1], 0, 10);
            }
        }

        return $data;
    }

    /**
     * Convert PHP DateTimeInterface to XML date string
     *
     * Returns date-only value in Y-m-d format, ignoring time component.
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
            // Return only the date portion for xsd:date
            return $data->format('Y-m-d');
        }

        if (is_string($data)) {
            try {
                $dateTime = new DateTimeImmutable($data);
                return $this->convertPhpToXml($dateTime);
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
