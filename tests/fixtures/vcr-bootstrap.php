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
 * What this guarantees is the class rewriting, which is permanent once it has
 * happened. It is not a standing safety net: IntegrationTestCase::tearDown() calls
 * VCR::turnOff(), and only its own setUp() turns VCR back on, so tests that run
 * after an integration test without extending that class execute with the hooks
 * disabled. Nothing in the unit suite issues a SOAP request today, and
 * ServiceInteractionTest asserts the rewriting is in force, but do not read this
 * bootstrap as a guarantee that a stray request cannot leave the machine.
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
