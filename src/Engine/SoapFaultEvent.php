<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Engine;

use Symfony\Contracts\EventDispatcher\Event;
use Throwable;

/**
 * Event dispatched when a SOAP fault occurs
 */
final class SoapFaultEvent extends Event
{
    public const NAME = 'soap.fault';

    public function __construct(
        private readonly string $method,
        private readonly Throwable $exception
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }
}
