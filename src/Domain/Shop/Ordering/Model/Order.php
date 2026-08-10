<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\SharedKernel\Event\DomainEventTrait;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Event\OrderPaidEvent;
use App\Domain\Shop\Ordering\Event\OrderPlacedEvent;
use App\Domain\Shop\Ordering\Exception\CartDomainException;
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
        private array $lines,
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
        array $lines,
        DateTimeImmutable $now,
        ?PaymentSessionId $paymentSessionId = null,
    ): self {
        self::assertLines($lines);

        $order = new self(
            id: $id,
            buyerId: $buyerId,
            reference: $reference,
            lines: $lines,
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
        array $lines,
        bool $isPaid,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?PaymentSessionId $paymentSessionId = null,
    ): self {
        self::assertLines($lines);

        return new self(
            id: $id,
            buyerId: $buyerId,
            reference: $reference,
            lines: $lines,
            isPaid: $isPaid,
            paymentSessionId: $paymentSessionId,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function markAsPaid(PaymentSessionId $paymentSessionId, DateTimeImmutable $now): void
    {
        if ($this->isPaid) {
            throw new CartDomainException('Order is already paid.');
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
        return $this->linesTotal();
    }

    public function linesTotal(): Money
    {
        $total = Money::zero($this->currency());

        foreach ($this->lines as $line) {
            $total = $total->add($line->total());
        }

        return $total;
    }

    private function currency(): string
    {
        return $this->lines[array_key_first($this->lines)]->getUnitPrice()->currency();
    }

    /**
     * @param OrderLine[] $lines
     */
    private static function assertLines(array $lines): void
    {
        if ([] === $lines) {
            throw new CartDomainException('Order must contain at least one line.');
        }

        $expectedCurrency = null;

        foreach ($lines as $line) {
            if (!$line instanceof OrderLine) {
                throw new InvalidArgumentException('Order lines must be of type OrderLine.');
            }

            $expectedCurrency ??= $line->getUnitPrice()->currency();

            if ($line->getUnitPrice()->currency() !== $expectedCurrency) {
                throw new CartDomainException('Order lines must share the same currency.');
            }
        }
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
