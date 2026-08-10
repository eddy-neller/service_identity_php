<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Event;

use App\Domain\SharedKernel\Event\DomainEventIdentityTrait;
use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\Shop\Ordering\ValueObject\OrderId;
use App\Domain\Shop\Ordering\ValueObject\OrderReference;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;

final class OrderPlacedEvent implements DomainEventInterface
{
    use DomainEventIdentityTrait;

    public function __construct(
        private readonly OrderId $orderId,
        private readonly OrderReference $reference,
        private readonly Money $total,
        private readonly DateTimeImmutable $occurredOn,
    ) {
        $this->eventId = self::generateEventId();
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function reference(): OrderReference
    {
        return $this->reference;
    }

    public function total(): Money
    {
        return $this->total;
    }

    public function aggregateId(): string
    {
        return $this->orderId->toString();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'shop.order.placed';
    }
}
