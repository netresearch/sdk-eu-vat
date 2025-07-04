<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\TypeConverter;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
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
 * IMPORTANT: Complex XML Parsing Required
 * ------------------------------------------------------------------------
 * Despite the WSDL defining situationOn as a simple xsd:date type, php-soap/ext-soap-engine
 * actually passes XML fragments to TypeConverters rather than clean scalar values.
 *
 * This was confirmed through testing:
 * - Expected input: "2025-01-01"
 * - Actual input: "<situationOn xmlns=\"urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService:types\">"
 *                 . "2025-01-01+01:00</situationOn>"
 *
 * The extractDateFromXml() and parseXmlSafely() methods are therefore ESSENTIAL for parsing
 * these XML fragments. Removing them causes test failures and runtime errors.
 *
 * This behavior appears to be inherent to php-soap/ext-soap-engine's TypeConverter system
 * when handling elements (as opposed to attributes) in SOAP responses.
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
     * NOTE: This method handles BOTH plain date strings AND XML fragments.
     * php-soap/ext-soap-engine passes XML elements like:
     * "<situationOn xmlns=\"...\">2025-01-01+01:00</situationOn>"
     *
     * The extractDateFromXml() method is required to parse these fragments.
     *
     * @param string $data Either a plain date string (YYYY-MM-DD) OR XML fragment
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
            // Handle XML-wrapped dates from SOAP responses
            $cleanedData = $this->extractDateFromXml($data);

            // Parse the date string - DateTimeImmutable will handle various formats
            $date = new DateTimeImmutable($cleanedData);

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
     * Extract date value from XML-wrapped content
     *
     * This method is ESSENTIAL because php-soap/ext-soap-engine passes XML fragments
     * to TypeConverters instead of clean scalar values, even for simple xsd:date types.
     *
     * Handles formats like:
     * - "<situationOn xmlns=\"...\">2025-01-01+01:00</situationOn>"
     * - "<ns1:situationOn>2024-01-15</ns1:situationOn>"
     * - Plain strings: "2024-01-15"
     *
     * @param string $data Raw data that might be XML-wrapped or plain string
     * @return string Clean date string (YYYY-MM-DD format)
     */
    private function extractDateFromXml(string $data): string
    {
        // Check if data contains XML tags
        if (!str_contains($data, '<') || !str_contains($data, '>')) {
            return $data;
        }

        // Try proper XML parsing first
        $cleanDate = $this->parseXmlSafely($data);
        if ($cleanDate !== null) {
            return $cleanDate;
        }

        // Fallback: Extract content between XML tags using regex
        if (preg_match('/>([^<]+)</', $data, $matches)) {
            return trim($matches[1]);
        }

        // Last resort: Extract date pattern from XML
        if (preg_match('/(\d{4}-\d{2}-\d{2}(?:\+\d{2}:\d{2})?)/', $data, $matches)) {
            // Remove timezone offset if present for date-only converter
            return substr($matches[1], 0, 10);
        }

        return $data;
    }

    /**
     * Safely parse XML content to extract date value
     *
     * @param string $xmlData XML content that might contain a date
     * @return string|null Clean date string or null if parsing fails
     */
    private function parseXmlSafely(string $xmlData): ?string
    {
        try {
            // Attempt to parse as XML fragment
            $previousSetting = libxml_use_internal_errors(true);

            // Try to wrap fragment in a root element if needed
            $wrappedXml = str_starts_with(trim($xmlData), '<')
                ? $xmlData
                : "<root>$xmlData</root>";

            $dom = new DOMDocument();
            $dom->loadXML($wrappedXml);

            // Get the text content (this handles CDATA, nested tags, etc.)
            $textContent = $dom->textContent;

            libxml_use_internal_errors($previousSetting);

            if ($textContent !== '' && $textContent !== '0') {
                $trimmed = trim($textContent);
                // Validate it looks like a date and extract just the date part
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $trimmed, $matches)) {
                    return $matches[1];
                }
                return $trimmed;
            }
        } catch (\Throwable) {
            // XML parsing failed, fall back to regex
            libxml_use_internal_errors($previousSetting ?? false);
        }

        return null;
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
                get_debug_type($data),
                is_scalar($data) ? (string) $data : 'non-scalar'
            )
        );
    }
}
