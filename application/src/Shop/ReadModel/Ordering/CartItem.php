<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Ordering;

use DateTimeImmutable;

final readonly class CartItem
{
    /** @param CartLineItem[] $items */
    public function __construct(
        public ?string $id,
        public array $items,
        public int $totalQuantity,
        public float $subtotal,
        public string $currency,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
