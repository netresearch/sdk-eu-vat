<?php

declare(strict_types=1);

namespace Netresearch\EuVatSdk\Tests\Unit\Factory;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber for factory tests
 */
class TestEventSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
