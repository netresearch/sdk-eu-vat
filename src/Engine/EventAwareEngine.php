<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Engine;

use Soap\Engine\Driver;
use Soap\Engine\Engine;
use Soap\Engine\Metadata\Metadata;
use Soap\Engine\Transport;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Event-aware SOAP engine wrapper
 *
 * This engine wrapper adds event dispatching capabilities to the standard SOAP engine,
 * allowing for request/response/fault event handling.
 */
final class EventAwareEngine implements Engine
{
    public function __construct(
        private readonly Driver $driver,
        private readonly Transport $transport,
        private readonly ?EventDispatcherInterface $dispatcher = null
    ) {
    }

    /**
     * @param array<mixed> $arguments
     */
    public function request(string $method, array $arguments): mixed
    {
        $startTime = microtime(true);

        // Dispatch request event if dispatcher is available
        if ($this->dispatcher instanceof EventDispatcherInterface) {
            $requestEvent = new SoapRequestEvent($method, $arguments);
            $this->dispatcher->dispatch($requestEvent, SoapRequestEvent::NAME);
        }

        try {
            $request = $this->driver->encode($method, $arguments);
            $response = $this->transport->request($request);
            $result = $this->driver->decode($method, $response);

            $endTime = microtime(true);

            // Dispatch response event if dispatcher is available
            if ($this->dispatcher instanceof EventDispatcherInterface) {
                $responseEvent = new SoapResponseEvent($method, $result, $startTime, $endTime);
                $this->dispatcher->dispatch($responseEvent, SoapResponseEvent::NAME);
            }

            return $result;
        } catch (\Throwable $exception) {
            $endTime = microtime(true);

            // Dispatch fault event if dispatcher is available
            if ($this->dispatcher instanceof EventDispatcherInterface) {
                $faultEvent = new SoapFaultEvent($method, $exception);
                $this->dispatcher->dispatch($faultEvent, SoapFaultEvent::NAME);
            }

            throw $exception;
        }
    }

    public function getMetadata(): Metadata
    {
        return $this->driver->getMetadata();
    }
}
