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
 * @package Netresearch\EuVatSdk\DTO\Response
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRateResult
{
    private readonly string $memberState;

    /**
     * @param string            $memberState ISO 3166-1 alpha-2 country code.
     * @param VatRate           $rate        The VAT rate information.
     * @param DateTimeInterface $situationOn The date for which this rate applies.
     * @param string|null       $comment     Optional comment from the EU VAT service.
     */
    public function __construct(
        string $memberState,
        private readonly VatRate $rate,
        private readonly DateTimeInterface $situationOn,
        private readonly ?string $comment = null
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
}
