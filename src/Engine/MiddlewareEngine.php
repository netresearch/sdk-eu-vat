<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Engine;

use Soap\Engine\Engine;
use Soap\Engine\Metadata\Metadata;
use Netresearch\EuVatSdk\Middleware\MiddlewareInterface;

/**
 * Middleware-aware SOAP engine wrapper
 *
 * This engine wrapper adds middleware pipeline support to any SOAP engine,
 * allowing for request/response processing through a chain of middleware.
 */
final class MiddlewareEngine implements Engine
{
    /**
     * @param Engine $innerEngine The actual SOAP engine
     * @param array<MiddlewareInterface> $middleware Array of middleware objects
     */
    public function __construct(
        private readonly Engine $innerEngine,
        private readonly array $middleware = []
    ) {
    }

    /**
     * @param array<mixed> $arguments
     */
    public function request(string $method, array $arguments): mixed
    {
        // Build the middleware chain
        $chain = fn(string $method, array $arguments) => $this->innerEngine->request($method, $arguments);

        // Wrap each middleware around the chain (in reverse order)
        foreach (array_reverse($this->middleware) as $middleware) {
            $chain = fn(string $method, array $arguments) => $middleware->process($method, $arguments, $chain);
        }

        // Execute the chain
        return $chain($method, $arguments);
    }

    public function getMetadata(): Metadata
    {
        return $this->innerEngine->getMetadata();
    }
}
