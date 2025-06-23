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
        private readonly mixed $result,
        private readonly float $startTime,
        private readonly float $endTime
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

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getEndTime(): float
    {
        return $this->endTime;
    }

    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }
}
