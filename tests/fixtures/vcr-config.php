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
    
    // Enable request matching by method, URL, and body
    ->setMode('once')
    
    // Configure request matching rules
    ->enableRequestMatchers(['method', 'url', 'body'])
    
    // Set up whitelist for allowed requests
    ->setWhiteList([
        'allow' => [
            'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService',
            'https://ec.europa.eu/taxation_customs/tedb/ws/VatRetrievalService-ACC',
            'https://ec.europa.eu/taxation_customs/tedb/ws-test/VatRetrievalService',
        ],
    ])
    
    // Configure SOAP-specific settings
    ->addRequestMatcher(
        'soap_action',
        function ($request1, $request2) {
            // Match SOAP action headers
            $action1 = $request1->getHeader('SOAPAction')[0] ?? '';
            $action2 = $request2->getHeader('SOAPAction')[0] ?? '';
            return $action1 === $action2;
        }
    );

// Enable library hooks for SOAP recording
VCR::enableLibraryHooks([
    'curl',
    'soap',
]);