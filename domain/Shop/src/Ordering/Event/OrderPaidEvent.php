<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Event;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\Shop\Ordering\ValueObject\OrderId;
use App\Domain\Shop\Ordering\ValueObject\OrderReference;
use DateTimeImmutable;

final class OrderPaidEvent implements DomainEventInterface
{
    public function __construct(
        private readonly OrderId $orderId,
        private readonly OrderReference $reference,
        private readonly DateTimeImmutable $occurredOn,
    ) {
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function reference(): OrderReference
    {
        return $this->reference;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'shop.order.paid';
    }
}
