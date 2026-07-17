<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\AddToCart;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class AddToCartCommand implements CommandInterface
{
    public function __construct(
        public string $customerId,
        public string $productId,
        public int $quantity,
    ) {
    }
}
