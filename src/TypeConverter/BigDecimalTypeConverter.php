<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\TypeConverter;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DOMDocument;
use Netresearch\EuVatSdk\Exception\ParseException;
use Soap\ExtSoapEngine\Configuration\TypeConverter\TypeConverterInterface;

/**
 * Type converter for xsd:double using BigDecimal for financial precision
 *
 * This converter handles automatic conversion between XML numeric literals and
 * Brick\Math\BigDecimal objects, ensuring precise financial calculations without
 * floating-point precision issues.
 *
 * IMPORTANT: The registered type is xsd:double, not xsd:decimal
 * ------------------------------------------------------------------------
 * ext-soap typemaps are keyed by the (namespace, type name) pair of the schema
 * type being (de)serialized. VatRetrievalServiceType.xsd declares the rate value
 * as `<xs:element minOccurs="0" name="value" type="xs:double"/>` and no schema in
 * resources/ uses xs:decimal at all. Registering for 'decimal' therefore matches
 * nothing: ext-soap decodes the value itself into a PHP float and the wire scale
 * ("17.0") is lost. Registering for 'double' is what makes this converter fire.
 *
 * IMPORTANT: ext-soap passes complete XML elements, not scalar text
 * ------------------------------------------------------------------------
 * The from_xml callback receives the serialized element, e.g.
 * "<value xmlns=\"urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService:types\">17.0</value>",
 * and the to_xml callback must return a complete element, not a bare scalar.
 * This mirrors php-soap/ext-soap-engine's own DoubleTypeConverter.
 *
 * NOTE: No range validation
 * ------------------------------------------------------------------------
 * This converter deliberately performs no VAT-plausibility check. It is a generic
 * XML-representation converter; rejecting a value here would abort deserialization
 * of the entire SOAP response (all member states) because of a single anomalous
 * rate. Domain-level plausibility belongs to the caller, not to the XML layer.
 *
 * @example XML to PHP conversion:
 * ```php
 * $converter = new BigDecimalTypeConverter();
 * $decimal = $converter->convertXmlToPhp('<value>19.75</value>');
 * // Returns: BigDecimal object representing 19.75 exactly
 * ```
 *
 * @example PHP to XML conversion:
 * ```php
 * $converter = new BigDecimalTypeConverter();
 * $xmlDecimal = $converter->convertPhpToXml(BigDecimal::of('19.75'));
 * // Returns: "<double>19.75</double>"
 * ```
 *
 * @package Netresearch\EuVatSdk\TypeConverter
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class BigDecimalTypeConverter implements TypeConverterInterface
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
     * CRITICAL: This must be 'double', the type the TEDB schema actually declares
     * for rate values. Changing it to 'decimal' silently disables the converter.
     *
     * @return string Always returns 'double' for xsd:double
     */
    public function getTypeName(): string
    {
        return 'double';
    }

    /**
     * Convert an XML numeric element to a PHP BigDecimal
     *
     * The literal text of the element is parsed verbatim, so the scale sent by the
     * service is preserved: "17.0" yields a BigDecimal of scale 1, not 17.
     *
     * @param string $data Complete XML element (e.g. '<value>19.75</value>') or a
     *                     plain numeric literal (e.g. '19.75')
     * @return BigDecimal|null BigDecimal for precise calculations, or null for an empty element
     * @throws ParseException If the numeric literal cannot be parsed
     *
     * @example
     * ```php
     * $decimal = $converter->convertXmlToPhp('<value>19.75</value>');
     * echo $decimal->__toString(); // "19.75"
     * ```
     */
    public function convertXmlToPhp(string $data): ?BigDecimal
    {
        $literal = $this->extractLiteral($data);

        if ($literal === '') {
            return null;
        }

        try {
            return BigDecimal::of($literal);
        } catch (MathException $e) {
            throw new ParseException(
                sprintf('Failed to parse decimal value: %s (invalid number format)', $literal),
                0,
                $e
            );
        }
    }

    /**
     * Convert PHP value to an XML numeric element
     *
     * CRITICAL: The ext-soap typemap contract requires a COMPLETE XML element,
     * not a bare scalar value. Returning a bare number causes ext-soap to serialize
     * an empty element, silently dropping the value from the request. ext-soap
     * replaces the returned element's name with the actual element name from the
     * WSDL, so the generic type name is used here.
     *
     * @param mixed $data BigDecimal, numeric value, or numeric string
     * @return string Complete XML element with the value's precision preserved
     * @throws ParseException If the input cannot be converted to a decimal
     *
     * @example
     * ```php
     * $xmlDecimal = $converter->convertPhpToXml(BigDecimal::of('19.75'));
     * echo $xmlDecimal; // "<double>19.75</double>"
     * ```
     */
    public function convertPhpToXml(mixed $data): string
    {
        if ($data instanceof BigDecimal) {
            return $this->wrapInElement($data->__toString());
        }

        if (is_numeric($data)) {
            try {
                return $this->wrapInElement(BigDecimal::of((string) $data)->__toString());
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
                return $this->wrapInElement(BigDecimal::of($data)->__toString());
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
                get_debug_type($data),
                is_scalar($data) ? (string) $data : 'non-scalar'
            )
        );
    }

    /**
     * Extract the numeric literal from an XML element or plain string
     *
     * php-soap/ext-soap-engine hands the complete serialized element to from_xml
     * callbacks, so the text content has to be read out before parsing. Plain
     * literals are passed through unchanged so the converter stays directly usable.
     *
     * @param string $data Complete XML element or plain numeric literal
     * @return string The trimmed literal, or an empty string for an empty element
     */
    private function extractLiteral(string $data): string
    {
        if (!str_contains($data, '<')) {
            return trim($data);
        }

        $previousSetting = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            if ($dom->loadXML($data)) {
                return trim($dom->textContent);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }

        // Fallback: take whatever sits between the first pair of tags
        if (preg_match('/>([^<]*)</', $data, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($data);
    }

    /**
     * Wrap a numeric literal in the XML element required by the ext-soap typemap
     *
     * Mirrors the contract of php-soap/ext-soap-engine's own DoubleTypeConverter:
     * sprintf('<%1$s>%2$s</%1$s>', $this->getTypeName(), $value)
     *
     * @param string $value Numeric literal produced by BigDecimal
     * @return string Complete XML element (e.g. "<double>19.75</double>")
     */
    private function wrapInElement(string $value): string
    {
        return sprintf(
            '<%1$s>%2$s</%1$s>',
            $this->getTypeName(),
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        );
    }
}
