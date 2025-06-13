<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\DTO\Request;

use DateTimeInterface;
use DateTime;
use Netresearch\EuVatSdk\Exception\ValidationException;

/**
 * Request DTO for retrieving VAT rates from the EU VAT service
 * 
 * This class represents a request to retrieve VAT rates for specific EU member states
 * on a given date. It includes validation to ensure the request meets the EU VAT
 * service requirements.
 * 
 * @example Basic usage:
 * ```php
 * $request = new VatRatesRequest(
 *     memberStates: ['DE', 'FR', 'IT'],
 *     situationOn: new DateTime('2024-01-01')
 * );
 * ```
 * 
 * @example With validation handling:
 * ```php
 * try {
 *     $request = new VatRatesRequest(['de', 'fr'], new DateTime()); // lowercase - will fail
 * } catch (ValidationException $e) {
 *     echo "Validation error: " . $e->getMessage();
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\DTO\Request
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class VatRatesRequest
{
    /**
     * @var string[] Normalized member state codes
     */
    private array $memberStates;
    
    /**
     * @param string[] $memberStates Array of ISO 3166-1 alpha-2 country codes
     * @param DateTimeInterface $situationOn Date for which rates are requested
     * @throws ValidationException If validation fails
     */
    public function __construct(
        array $memberStates,
        private readonly DateTimeInterface $situationOn
    ) {
        $this->validateAndNormalizeMemberStates($memberStates);
        $this->validateDate($situationOn);
    }
    
    /**
     * Get the normalized member state codes
     * 
     * @return string[] Array of uppercase ISO 3166-1 alpha-2 country codes
     */
    public function getMemberStates(): array
    {
        return $this->memberStates;
    }
    
    /**
     * Get the situation date
     * 
     * @return DateTimeInterface The date for which VAT rates are requested
     */
    public function getSituationOn(): DateTimeInterface
    {
        return $this->situationOn;
    }
    
    /**
     * Validate and normalize member state codes
     * 
     * @param string[] $memberStates
     * @throws ValidationException
     */
    private function validateAndNormalizeMemberStates(array $memberStates): void
    {
        if (empty($memberStates)) {
            throw new ValidationException('Member states array cannot be empty.');
        }
        
        $validatedStates = [];
        $seen = [];
        
        foreach ($memberStates as $code) {
            if (!is_string($code)) {
                throw new ValidationException(
                    sprintf('Invalid member state code type: expected string, got %s', gettype($code))
                );
            }
            
            $normalized = strtoupper(trim($code));
            
            if (strlen($normalized) !== 2) {
                throw new ValidationException(
                    sprintf('Invalid member state code length: %s (must be exactly 2 characters)', $code)
                );
            }
            
            if (!ctype_upper($normalized)) {
                throw new ValidationException(
                    sprintf('Invalid member state code format: %s (must contain only uppercase letters)', $code)
                );
            }
            
            // Prevent duplicates
            if (isset($seen[$normalized])) {
                continue;
            }
            
            $seen[$normalized] = true;
            $validatedStates[] = $normalized;
        }
        
        $this->memberStates = $validatedStates;
    }
    
    /**
     * Validate the date is within reasonable bounds
     * 
     * @param DateTimeInterface $date
     * @throws ValidationException
     */
    private function validateDate(DateTimeInterface $date): void
    {
        $now = new DateTime();
        $maxFuture = new DateTime('+5 years');
        $minPast = new DateTime('2000-01-01'); // EU VAT data availability
        
        if ($date > $maxFuture) {
            throw new ValidationException(
                'Date cannot be more than 5 years in the future'
            );
        }
        
        if ($date < $minPast) {
            throw new ValidationException(
                'Date cannot be before January 1, 2000 (EU VAT data availability limit)'
            );
        }
    }
}