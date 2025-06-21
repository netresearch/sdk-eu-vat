<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Middleware;

/**
 * Interface for SOAP request middleware
 *
 * Middleware allows intercepting and processing SOAP requests/responses
 * in a pipeline fashion. Each middleware can transform the request,
 * perform side effects (logging, monitoring), or modify the response.
 *
 * @package Netresearch\EuVatSdk\Middleware
 * @author  Netresearch DTT GmbH
 * @license https://opensource.org/licenses/MIT MIT License
 */
interface MiddlewareInterface
{
    /**
     * Process SOAP request through middleware pipeline
     *
     * @param string $method SOAP method being called
     * @param array<mixed> $arguments Method arguments
     * @param callable $next Next handler in the pipeline
     * @return mixed Result from the operation
     */
    public function process(string $method, array $arguments, callable $next): mixed;
}
