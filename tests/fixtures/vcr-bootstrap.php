<?php

/**
 * PHPUnit bootstrap for the EU VAT SDK test suites
 *
 * php-vcr intercepts SOAP by rewriting the parent class of the ext-soap transport
 * while the file is being included: `Soap\ExtSoapEngine\AbusedClient` normally
 * extends `\SoapClient`, and VCR swaps that parent for `\VCR\Util\SoapClient`. A
 * class that PHP has already loaded can no longer be rewritten.
 *
 * That makes the order of the very first include decisive. When a suite that does
 * not use VCR runs first in the same process -- as happens for `composer test`,
 * where the unit, integration and all suites share one process -- the transport is
 * loaded unpatched and every later "replayed" call silently travels to the live EU
 * VAT service instead. Turning VCR on here, before any test file is loaded,
 * guarantees the transport is patched for the whole process.
 *
 * With VCR on and no cassette inserted, an unexpected request fails loudly instead
 * of leaving the machine, which is exactly what the unit suite wants.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

use VCR\VCR;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/vcr-config.php';

VCR::turnOn();
