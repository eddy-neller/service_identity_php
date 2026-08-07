<?php

declare(strict_types=1);

namespace App\Domain\SharedKernel\Tests\Unit\Event;

use App\Domain\SharedKernel\Event\DomainEventIdentityTrait;
use App\Domain\SharedKernel\Event\DomainEventInterface;
use DateTimeImmutable;

/**
 * Événement de test : exerce `DomainEventIdentityTrait` sans dépendre d'un bounded context.
 */
final readonly class FakeDomainEvent implements DomainEventInterface
{
    use DomainEventIdentityTrait;

    private DateTimeImmutable $occurredOn;

    public function __construct()
    {
        $this->occurredOn = new DateTimeImmutable('2026-08-06 12:00:00');
        $this->eventId = self::generateEventId();
    }

    public function aggregateId(): string
    {
        return 'shared-kernel-fake-aggregate';
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'shared_kernel.fake';
    }
}
