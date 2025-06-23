<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Converter;

use Brick\Math\BigDecimal;
use DateTimeInterface;
use Netresearch\EuVatSdk\DTO\Response\VatRate;
use Netresearch\EuVatSdk\DTO\Response\VatRateResult;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\ConversionException;
use stdClass;

/**
 * Converts raw SOAP response stdClass to VatRatesResponse DTO
 *
 * This converter handles the transformation from the stdClass object that
 * php-soap/ext-soap-engine produces to our strongly-typed immutable DTOs.
 * It works in conjunction with TypeConverters which handle primitive type
 * conversion (dates, decimals) at the SOAP engine level.
 *
 * The converter expects:
 * - DateTypeConverter to have already converted xsd:date to DateTimeInterface
 * - BigDecimalTypeConverter to have already converted xsd:decimal to BigDecimal
 * - ClassMap to be disabled (we handle all object construction manually)
 *
 * @package Netresearch\EuVatSdk\Converter
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRatesResponseConverter
{
    /**
     * Converts SOAP response stdClass to VatRatesResponse DTO
     *
     * @param stdClass $response Raw SOAP response object
     * @return VatRatesResponse Strongly-typed response DTO
     * @throws ConversionException If response structure is invalid or type conversion fails
     */
    public function convert(stdClass $response): VatRatesResponse
    {
        // Validate the expected response structure based on XSD schema
        if (!isset($response->vatRateResults)) {
            throw new ConversionException('Response is missing expected "vatRateResults" property.');
        }

        $rawResults = $response->vatRateResults;

        // Handle SOAP single-item vs array quirk: single result is object, multiple results are array
        $resultsList = is_array($rawResults) ? $rawResults : [$rawResults];

        $vatRateResults = [];
        foreach ($resultsList as $index => $resultData) {
            if (!$resultData instanceof stdClass) {
                throw new ConversionException(
                    sprintf(
                        'Invalid item at index %d in vatRateResults; expected stdClass, got %s',
                        $index,
                        get_debug_type($resultData)
                    )
                );
            }

            $vatRateResults[] = $this->createVatRateResult($resultData);
        }

        return new VatRatesResponse($vatRateResults);
    }

    /**
     * Creates a single VatRateResult DTO from stdClass data
     *
     * This method handles the conversion of a single VAT rate result,
     * validating all required fields and ensuring type safety.
     *
     * @param stdClass $data Raw VAT rate result data
     * @return VatRateResult Strongly-typed VAT rate result DTO
     * @throws ConversionException If required fields are missing or types are incorrect
     */
    private function createVatRateResult(stdClass $data): VatRateResult
    {
        try {
            // Extract and validate required fields based on XSD schema
            $memberState = $data->memberState
                ?? throw new ConversionException('Missing "memberState" for VAT rate result.');
            $situationOn = $data->situationOn
                ?? throw new ConversionException('Missing "situationOn" for VAT rate result.');

            // Validate that rate object exists
            if (!isset($data->rate) || !$data->rate instanceof stdClass) {
                throw new ConversionException('Missing or invalid "rate" object for VAT rate result.');
            }

            $vatRate = $this->createVatRate($data->rate);

            // Defensive type check: Verify TypeConverter did its job for the situationOn field
            if (!$situationOn instanceof DateTimeInterface) {
                throw new ConversionException(
                    sprintf(
                        'Expected "situationOn" to be a DateTimeInterface object, got %s. ' .
                        'Check DateTypeConverter configuration.',
                        get_debug_type($situationOn)
                    )
                );
            }

            // Extract optional comment field
            $comment = isset($data->comment) && is_string($data->comment) && $data->comment !== ''
                ? $data->comment
                : null;

            return new VatRateResult(
                memberState: (string) $memberState,
                rate: $vatRate,
                situationOn: $situationOn,
                comment: $comment
            );
        } catch (\Throwable $e) {
            if ($e instanceof ConversionException) {
                throw $e; // Re-throw our specific exceptions without wrapping
            }

            // Wrap other exceptions (e.g., from DTO validation) to provide context
            throw new ConversionException(
                'Failed to create VatRateResult DTO: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Creates a VatRate DTO from stdClass rate data
     *
     * @param stdClass $rateData Raw rate data from SOAP response
     * @return VatRate Strongly-typed VAT rate DTO
     * @throws ConversionException If rate data is invalid
     */
    private function createVatRate(stdClass $rateData): VatRate
    {
        try {
            $type = $rateData->type
                ?? throw new ConversionException('Missing "type" for VAT rate.');
            $value = $rateData->value
                ?? throw new ConversionException('Missing "value" for VAT rate.');

            // Defensive type check: Verify BigDecimalTypeConverter did its job
            if (!$value instanceof BigDecimal) {
                // Fallback: try to convert string to BigDecimal if TypeConverter failed
                if (!is_string($value) && !is_numeric($value)) {
                    throw new ConversionException(
                        sprintf(
                            'Expected "value" to be a BigDecimal object or convertible numeric, got %s. ' .
                            'Check BigDecimalTypeConverter configuration.',
                            get_debug_type($value)
                        )
                    );
                }

                $value = BigDecimal::of((string) $value);
            }

            // Extract optional category - this comes from the parent result type, not the rate itself
            // Based on XSD analysis, category is part of the vatRateResults, not the rate element
            $category = null; // Will be handled at VatRateResult level if needed

            return new VatRate(
                type: (string) $type,
                value: $value->__toString(), // Convert BigDecimal to string for VatRate constructor
                category: $category
            );
        } catch (\Throwable $e) {
            if ($e instanceof ConversionException) {
                throw $e;
            }

            throw new ConversionException(
                'Failed to create VatRate DTO: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
