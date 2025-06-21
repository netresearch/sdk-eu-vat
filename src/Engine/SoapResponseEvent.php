<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Engine;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a SOAP response is received
 */
final class SoapResponseEvent extends Event
{
    public const NAME = 'soap.response';

    public function __construct(
        private readonly string $method,
        private readonly mixed $result
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }
}
