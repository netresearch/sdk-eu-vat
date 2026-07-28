<?php

declare(strict_types=1);

use VCR\VCR;

/**
 * PHP-VCR Configuration for EU VAT SDK Integration Tests
 *
 * This configuration sets up VCR for recording and replaying HTTP/SOAP interactions
 * with the EU VAT Retrieval Service, enabling reliable offline testing.
 *
 * @package Netresearch\EuVatSdk\Tests
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */

// Configure VCR for SOAP recording
VCR::configure()
    // Set the storage path for cassettes
    ->setCassettePath(__DIR__ . '/cassettes')

    // Set the storage format to JSON for better readability
    ->setStorage('json')

    // Replay existing cassettes; only record when a cassette does not exist yet
    ->setMode('once')

    // Configure request matching rules. The SOAP envelope in the body already
    // encodes the operation and its arguments, so method + URL + body uniquely
    // identifies a request. php-vcr also ships a built-in 'soap_operation'
    // matcher should finer-grained matching ever be needed.
    ->enableRequestMatchers(['method', 'url', 'body'])

    // Enable library hooks for SOAP and cURL recording
    ->enableLibraryHooks(['curl', 'soap']);
