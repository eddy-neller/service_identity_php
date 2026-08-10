<?php

declare(strict_types=1);

namespace App\Tests\Domain\SharedKernel\Unit\Event;

use PHPUnit\Framework\TestCase;

final class DomainEventIdentityTraitTest extends TestCase
{
    public function testEachEventGetsItsOwnIdentity(): void
    {
        $first = new FakeDomainEvent();
        $second = new FakeDomainEvent();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first->eventId());
        $this->assertNotSame($first->eventId(), $second->eventId());
    }

    public function testIdentityIsStableForAGivenInstance(): void
    {
        $event = new FakeDomainEvent();

        $this->assertSame($event->eventId(), $event->eventId());
    }

    /**
     * Propriété dont dépend la déduplication : une redélivrance Messenger déshydrate
     * le même message, l'identifiant doit donc traverser la sérialisation intact.
     */
    public function testIdentitySurvivesSerialization(): void
    {
        $event = new FakeDomainEvent();

        $restored = unserialize(serialize($event));

        $this->assertInstanceOf(FakeDomainEvent::class, $restored);
        $this->assertSame($event->eventId(), $restored->eventId());
    }
}
