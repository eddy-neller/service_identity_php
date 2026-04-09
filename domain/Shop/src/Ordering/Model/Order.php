<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\SharedKernel\Event\DomainEventTrait;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Event\OrderPaidEvent;
use App\Domain\Shop\Ordering\Event\OrderPlacedEvent;
use App\Domain\Shop\Ordering\ValueObject\CarrierSelection;
use App\Domain\Shop\Ordering\ValueObject\DeliveryAddress;
use App\Domain\Shop\Ordering\ValueObject\OrderId;
use App\Domain\Shop\Ordering\ValueObject\OrderReference;
use App\Domain\Shop\Ordering\ValueObject\PaymentSessionId;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class Order
{
    use DomainEventTrait;

    /**
     * @param OrderLine[] $lines
     */
    private function __construct(
        private OrderId $id,
        private CustomerId $buyerId,
        private OrderReference $reference,
        private CarrierSelection $carrier,
        private array $lines,
        private DeliveryAddress $delivery,
        private bool $isPaid,
        private ?PaymentSessionId $paymentSessionId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param OrderLine[] $lines
     */
    public static function place(
        OrderId $id,
        CustomerId $buyerId,
        OrderReference $reference,
        CarrierSelection $carrier,
        DeliveryAddress $delivery,
        array $lines,
        DateTimeImmutable $now,
        ?PaymentSessionId $paymentSessionId = null,
    ): self {
        self::assertLines($lines, $carrier);

        $order = new self(
            id: $id,
            buyerId: $buyerId,
            reference: $reference,
            carrier: $carrier,
            lines: $lines,
            delivery: $delivery,
            isPaid: false,
            paymentSessionId: $paymentSessionId,
            createdAt: $now,
            updatedAt: $now,
        );

        $order->recordEvent(new OrderPlacedEvent(
            orderId: $id,
            reference: $reference,
            total: $order->total(),
            occurredOn: $now,
        ));

        return $order;
    }

    /**
     * @param OrderLine[] $lines
     */
    public static function reconstitute(
        OrderId $id,
        CustomerId $buyerId,
        OrderReference $reference,
        CarrierSelection $carrier,
        DeliveryAddress $delivery,
        array $lines,
        bool $isPaid,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?PaymentSessionId $paymentSessionId = null,
    ): self {
        self::assertLines($lines, $carrier);

        return new self(
            id: $id,
            buyerId: $buyerId,
            reference: $reference,
            carrier: $carrier,
            lines: $lines,
            delivery: $delivery,
            isPaid: $isPaid,
            paymentSessionId: $paymentSessionId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function markAsPaid(PaymentSessionId $paymentSessionId, DateTimeImmutable $now): void
    {
        if ($this->isPaid) {
            throw new InvalidArgumentException('Order is already paid.');
        }

        $this->isPaid = true;
        $this->paymentSessionId = $paymentSessionId;
        $this->touch($now);

        $this->recordEvent(new OrderPaidEvent(
            orderId: $this->id,
            reference: $this->reference,
            occurredOn: $now,
        ));
    }

    public function assignPaymentSession(PaymentSessionId $paymentSessionId, DateTimeImmutable $now): void
    {
        $this->paymentSessionId = $paymentSessionId;
        $this->touch($now);
    }

    /**
     * @return OrderLine[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getBuyerId(): CustomerId
    {
        return $this->buyerId;
    }

    public function getReference(): OrderReference
    {
        return $this->reference;
    }

    public function getCarrier(): CarrierSelection
    {
        return $this->carrier;
    }

    public function getDelivery(): DeliveryAddress
    {
        return $this->delivery;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function getPaymentSessionId(): ?PaymentSessionId
    {
        return $this->paymentSessionId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function total(): Money
    {
        $linesTotal = $this->linesTotal();

        return $linesTotal->add($this->carrier->getPrice());
    }

    public function linesTotal(): Money
    {
        $currency = $this->carrier->getPrice()->currency();
        $total = Money::zero($currency);

        foreach ($this->lines as $line) {
            $total = $total->add($line->total());
        }

        return $total;
    }

    /**
     * @param OrderLine[] $lines
     */
    private static function assertLines(array $lines, CarrierSelection $carrier): void
    {
        if ([] === $lines) {
            throw new InvalidArgumentException('Order must contain at least one line.');
        }

        $expectedCurrency = $carrier->getPrice()->currency();

        foreach ($lines as $line) {
            if (!$line instanceof OrderLine) {
                throw new InvalidArgumentException('Order lines must be of type OrderLine.');
            }

            if ($line->getUnitPrice()->currency() !== $expectedCurrency) {
                throw new InvalidArgumentException('Order line currency must match carrier currency.');
            }
        }
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
