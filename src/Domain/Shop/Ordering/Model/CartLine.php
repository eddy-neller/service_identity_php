<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Domain\Shop\Ordering\ValueObject\CartLineQuantity;

final class CartLine
{
    private function __construct(
        private readonly CartLineId $id,
        private readonly ProductId $productId,
        private CartLineQuantity $quantity,
    ) {
    }

    public static function create(CartLineId $id, ProductId $productId, CartLineQuantity $quantity): self
    {
        return new self($id, $productId, $quantity);
    }

    public function increase(CartLineQuantity $quantity): void
    {
        $this->quantity = $this->quantity->add($quantity);
    }

    public function setQuantity(CartLineQuantity $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getId(): CartLineId
    {
        return $this->id;
    }

    public function getProductId(): ProductId
    {
        return $this->productId;
    }

    public function getQuantity(): CartLineQuantity
    {
        return $this->quantity;
    }
}
