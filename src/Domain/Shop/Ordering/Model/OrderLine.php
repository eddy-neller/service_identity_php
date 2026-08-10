<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Ordering\ValueObject\OrderLineId;
use App\Domain\Shop\Ordering\ValueObject\OrderLineQuantity;
use App\Domain\Shop\Shared\ValueObject\Money;

final class OrderLine
{
    private function __construct(
        private readonly OrderLineId $id,
        private readonly ProductTitle $productName,
        private readonly OrderLineQuantity $quantity,
        private readonly Money $unitPrice,
    ) {
    }

    public static function create(
        OrderLineId $id,
        ProductTitle $productName,
        Money $unitPrice,
        OrderLineQuantity $quantity,
    ): self {
        return new self(
            id: $id,
            productName: $productName,
            quantity: $quantity,
            unitPrice: $unitPrice,
        );
    }

    public function getId(): OrderLineId
    {
        return $this->id;
    }

    public function getProductName(): ProductTitle
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
