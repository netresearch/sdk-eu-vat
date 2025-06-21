<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Engine;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a SOAP request is made
 */
final class SoapRequestEvent extends Event
{
    public const NAME = 'soap.request';

    /**
     * @param array<mixed> $arguments
     */
    public function __construct(
        private readonly string $method,
        private readonly array $arguments
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
