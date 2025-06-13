<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Client;

use Netresearch\EuVatSdk\DTO\Request\VatRatesRequest;
use Netresearch\EuVatSdk\DTO\Response\VatRatesResponse;
use Netresearch\EuVatSdk\Exception\VatServiceException;
use Netresearch\EuVatSdk\Exception\SoapFaultException;
use Netresearch\EuVatSdk\Exception\InvalidRequestException;
use Netresearch\EuVatSdk\Exception\ServiceUnavailableException;
use Netresearch\EuVatSdk\Exception\ConfigurationException;

/**
 * Main interface for retrieving VAT rates from the EU VAT Retrieval Service
 * 
 * This interface defines the primary API for interacting with the European Union's
 * official VAT Retrieval Service SOAP API. Implementations provide clean abstraction
 * over SOAP complexity while offering comprehensive error handling and observability.
 * 
 * @example Basic usage:
 * ```php
 * $client = VatRetrievalClientFactory::create();
 * 
 * $request = new VatRatesRequest(['DE', 'FR'], new DateTimeImmutable('2024-01-15'));
 * $response = $client->retrieveVatRates($request);
 * 
 * foreach ($response->getResults() as $result) {
 *     echo $result->getMemberState() . ': ' . $result->getVatRate()->getValue() . '%' . PHP_EOL;
 * }
 * ```
 * 
 * @example Enterprise usage with error handling:
 * ```php
 * try {
 *     $response = $client->retrieveVatRates($request);
 *     // Process successful response
 * } catch (InvalidRequestException $e) {
 *     // Handle client-side validation errors (invalid country codes, dates)
 *     $logger->warning('Invalid VAT rate request: ' . $e->getMessage());
 * } catch (ServiceUnavailableException $e) {
 *     // Handle service availability issues
 *     $logger->error('EU VAT service unavailable: ' . $e->getMessage());
 * } catch (SoapFaultException $e) {
 *     // Handle SOAP-specific errors
 *     $logger->error('SOAP fault: ' . $e->getFaultCode() . ' - ' . $e->getFaultString());
 * } catch (VatServiceException $e) {
 *     // Handle any other SDK-specific errors
 *     $logger->error('VAT service error: ' . $e->getMessage());
 * }
 * ```
 * 
 * @package Netresearch\EuVatSdk\Client
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
interface VatRetrievalClientInterface
{
    /**
     * Retrieve VAT rates for specified EU member states on a given date
     * 
     * Queries the EU VAT Retrieval Service to obtain current VAT rates for the
     * specified member states as they were/are effective on the given date.
     * 
     * @param VatRatesRequest $request Request containing member states and situation date
     * @return VatRatesResponse Response containing VAT rates for all requested member states
     * 
     * @throws InvalidRequestException When request validation fails:
     *         - Invalid country codes (non-EU members, malformed codes)
     *         - Invalid date format or dates too far in the future
     *         - Empty member states array
     *         - Error codes: TEDB-100, TEDB-101, TEDB-102
     * 
     * @throws ServiceUnavailableException When service is unavailable:
     *         - Network connectivity issues
     *         - Service temporarily down for maintenance
     *         - Internal application errors in EU service
     *         - Error codes: TEDB-400
     * 
     * @throws SoapFaultException When SOAP-level errors occur:
     *         - Malformed SOAP requests/responses
     *         - WSDL parsing errors
     *         - Unexpected SOAP faults from service
     * 
     * @throws ConfigurationException When client configuration is invalid:
     *         - Invalid endpoint URLs
     *         - Missing or corrupted WSDL files
     *         - Invalid SOAP client options
     * 
     * @throws VatServiceException For any other service-related errors:
     *         - Unexpected response formats
     *         - Data parsing failures
     *         - Internal SDK errors
     * 
     * @example Retrieve VAT rates for Germany and France:
     * ```php
     * $request = new VatRatesRequest(
     *     ['DE', 'FR'], 
     *     new DateTimeImmutable('2024-01-15')
     * );
     * $response = $client->retrieveVatRates($request);
     * 
     * foreach ($response->getResults() as $result) {
     *     printf(
     *         '%s: %s%% (%s rate)\n',
     *         $result->getMemberState(),
     *         $result->getVatRate()->getValue(),
     *         $result->getVatRate()->getType()
     *     );
     * }
     * ```
     * 
     * @example Historical VAT rate lookup:
     * ```php
     * $historicalDate = new DateTimeImmutable('2020-01-01');
     * $request = new VatRatesRequest(['AT', 'BE'], $historicalDate);
     * $response = $client->retrieveVatRates($request);
     * ```
     */
    public function retrieveVatRates(VatRatesRequest $request): VatRatesResponse;
}