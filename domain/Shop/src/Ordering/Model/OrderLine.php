<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\Shop\Ordering\ValueObject\OrderLineId;
use App\Domain\Shop\Ordering\ValueObject\OrderLineQuantity;
use App\Domain\Shop\Shared\ValueObject\Money;
use InvalidArgumentException;

final class OrderLine
{
    private function __construct(
        private readonly OrderLineId $id,
        private readonly string $productName,
        private readonly OrderLineQuantity $quantity,
        private readonly Money $unitPrice,
    ) {
    }

    public static function create(
        OrderLineId $id,
        string $productName,
        Money $unitPrice,
        OrderLineQuantity $quantity,
    ): self {
        $trimmedName = trim($productName);

        if ('' === $trimmedName) {
            throw new InvalidArgumentException('Order line product name cannot be empty.');
        }

        return new self(
            id: $id,
            productName: $trimmedName,
            quantity: $quantity,
            unitPrice: $unitPrice,
        );
    }

    public function getId(): OrderLineId
    {
        return $this->id;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): OrderLineQuantity
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity->toInt());
    }
}
