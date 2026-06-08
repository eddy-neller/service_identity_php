<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\Model;

use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Ordering\Exception\CartQuantityExceededException;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;

final class CartLine
{
    private function __construct(
        private readonly CartLineId $id,
        private readonly ProductId $productId,
        private int $quantity,
    ) {
    }

    public static function create(CartLineId $id, ProductId $productId, int $quantity): self
    {
        self::assertQuantity($quantity);

        return new self($id, $productId, $quantity);
    }

    public function increase(int $quantity): void
    {
        self::assertQuantity($quantity);
        $this->setQuantity($this->quantity + $quantity);
    }

    public function setQuantity(int $quantity): void
    {
        self::assertQuantity($quantity);
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    private static function assertQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99) {
            throw new CartQuantityExceededException();
        }
    }
}
