<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\TypeConverter;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Netresearch\EuVatSdk\Exception\ParseException;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;
use Throwable;

/**
 * Type converter for xsd:dateTime (date with time component)
 * 
 * This converter handles automatic conversion between XML dateTime strings and
 * PHP DateTimeImmutable objects. It includes full date and time information
 * with timezone handling.
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
     * @return string Always returns 'dateTime' for xsd:dateTime
     */
    public function getTypeName(): string
    {
        return 'dateTime';
    }

    /**
     * Convert XML dateTime string to PHP DateTimeImmutable
     * 
     * @param string $data XML dateTime string (e.g., '2024-01-15T14:30:00' or '2024-01-15T14:30:00Z')
     * @return DateTimeImmutable PHP dateTime object
     * @throws ParseException If the dateTime string cannot be parsed
     * 
     * @example
     * ```php
     * $dateTime = $converter->convertXmlToPhp('2024-01-15T14:30:00Z');
     * echo $dateTime->format('Y-m-d H:i:s'); // "2024-01-15 14:30:00"
     * ```
     */
    public function convertXmlToPhp(string $data): DateTimeImmutable
    {
        try {
            // Handle various XML dateTime formats including timezone indicators
            $dateTime = new DateTimeImmutable($data);
            
            return $dateTime;
        } catch (Throwable $e) {
            throw new ParseException(
                sprintf(
                    'Failed to parse dateTime value: %s (expected format: YYYY-MM-DDTHH:MM:SS or YYYY-MM-DDTHH:MM:SSZ)',
                    $data
                ),
                0,
                $e
            );
        }
    }

    /**
     * Convert PHP DateTimeInterface to XML dateTime string
     * 
     * Returns full date and time in ISO 8601 format (Y-m-d\TH:i:s).
     * Timezone information is handled appropriately for SOAP services.
     * 
     * @param mixed $data DateTimeInterface or dateTime string
     * @return string XML dateTime string in YYYY-MM-DDTHH:MM:SS format
     * @throws ParseException If the input cannot be converted to a dateTime
     * 
     * @example
     * ```php
     * $xmlDateTime = $converter->convertPhpToXml(new DateTime('2024-01-15 14:30:00'));
     * echo $xmlDateTime; // "2024-01-15T14:30:00"
     * ```
     */
    public function convertPhpToXml($data): string
    {
        if ($data instanceof DateTimeInterface) {
            // Convert to UTC if timezone is available
            if ($data->getTimezone()->getName() !== 'UTC') {
                $utcDateTime = $data->setTimezone(new DateTimeZone('UTC'));
                return $utcDateTime->format('Y-m-d\TH:i:s\Z');
            }
            
            // Use ISO 8601 format with T separator
            return $data->format('Y-m-d\TH:i:s');
        }
        
        if (is_string($data)) {
            try {
                $dateTime = new DateTimeImmutable($data);
                return $this->convertPhpToXml($dateTime);
            } catch (Throwable $e) {
                throw new ParseException(
                    sprintf('Failed to parse dateTime string: %s', $data),
                    0,
                    $e
                );
            }
        }
        
        throw new ParseException(
            sprintf(
                'Cannot convert %s to XML dateTime. Expected DateTimeInterface or string, got: %s',
                is_object($data) ? get_class($data) : gettype($data),
                is_scalar($data) ? (string) $data : 'non-scalar'
            )
        );
    }
}