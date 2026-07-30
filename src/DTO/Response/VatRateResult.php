<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\DTO\Response;

use DateTimeInterface;

/**
 * Value object representing a single VAT rate result for a member state
 *
 * This class encapsulates the VAT rate information for a specific member state
 * on a given date, including any additional comments from the EU VAT service.
 *
 * @example Basic usage:
 * ```php
 * $rate = new VatRate('STANDARD', '19.0');
 * $result = new VatRateResult(
 *     memberState: 'DE',
 *     rate: $rate,
 *     situationOn: new DateTime('2024-01-01'),
 *     comment: 'Standard rate applies'
 * );
 *
 * echo $result->getMemberState(); // "DE"
 * echo $result->getRate()->getValue(); // "19.0"
 * echo $result->getComment(); // "Standard rate applies"
 * ```
 *
 * @example A result carrying a category:
 * ```php
 * $result = new VatRateResult(
 *     memberState: 'DE',
 *     rate: new VatRate('REDUCED_RATE', '7.0'),
 *     situationOn: new DateTime('2024-01-01'),
 *     comment: null,
 *     category: 'FOODSTUFFS',
 *     categoryDescription: 'Foodstuffs (including beverages but excluding alcoholic beverages) ...'
 * );
 *
 * echo $result->getCategory(); // "FOODSTUFFS"
 * ```
 *
 * The service reports the category per result, not per rate: a reduced rate is
 * returned once for every category it applies to, while standard rates arrive
 * without a category at all. Both getCategory() and getCategoryDescription()
 * therefore return null for a sizeable share of real results.
 *
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRateResult
{
    private readonly string $memberState;

    /**
     * @param string            $memberState         ISO 3166-1 alpha-2 country code.
     * @param VatRate           $rate                The VAT rate information.
     * @param DateTimeInterface $situationOn         The date for which this rate applies.
     * @param string|null       $comment             Optional comment from the EU VAT service.
     * @param string|null       $category            Optional category identifier (e.g. 'FOODSTUFFS').
     *                                               The XSD declares the category element with
     *                                               minOccurs="0", and the service omits it for
     *                                               standard rates, so null is a normal value.
     * @param string|null       $categoryDescription Human-readable description of the category. The
     *                                               XSD requires it inside a category element, so it
     *                                               is non-null exactly when $category is non-null.
     */
    public function __construct(
        string $memberState,
        private readonly VatRate $rate,
        private readonly DateTimeInterface $situationOn,
        private readonly ?string $comment = null,
        private readonly ?string $category = null,
        private readonly ?string $categoryDescription = null
    ) {
        $this->memberState = strtoupper(trim($memberState));
    }

    /**
     * Get the member state code
     *
     * @return string The ISO 3166-1 alpha-2 country code
     */
    public function getMemberState(): string
    {
        return $this->memberState;
    }

    /**
     * Get the rate type from the VAT rate
     *
     * @return string The rate type (e.g., 'STANDARD', 'REDUCED')
     */
    public function getType(): string
    {
        return $this->rate->getType();
    }

    /**
     * Get the VAT rate
     *
     * Note: the returned VatRate may carry no percentage value for any rate type —
     * its getValue() returns null then. The XSD declares the value element optional
     * for every member of rateValueTypeEnum.
     *
     * @return VatRate The VAT rate information
     */
    public function getRate(): VatRate
    {
        return $this->rate;
    }

    /**
     * Get the situation date
     *
     * @return DateTimeInterface The date for which this rate applies
     */
    public function getSituationOn(): DateTimeInterface
    {
        return $this->situationOn;
    }

    /**
     * Get the comment if available
     *
     * @return string|null Additional information from the EU VAT service
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Get the category identifier if available
     *
     * The category names the goods or services the rate applies to, for example
     * 'FOODSTUFFS' or 'SUPPLY_ELECTRICITY'. The identifiers are enumerated in the
     * External Interface Specification (EIS) of TEDB, not in the WSDL.
     *
     * @return string|null The category identifier, or null when the service reported
     *                     no category for this result (standard rates never carry one)
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Get the human-readable category description if available
     *
     * @return string|null The category description, or null when the service reported
     *                     no category for this result
     */
    public function getCategoryDescription(): ?string
    {
        return $this->categoryDescription;
    }
}
