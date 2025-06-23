<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Exception;

/**
 * Exception thrown when SOAP response conversion to DTO fails
 *
 * This exception indicates that the raw SOAP response data could not be
 * properly converted to the expected DTO structure. This typically occurs when:
 * - Required fields are missing from the response
 * - Field values have unexpected types or formats
 * - DTO validation rules are violated during construction
 *
 * @package Netresearch\EuVatSdk\Exception
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
final class ConversionException extends VatServiceException
{
}
