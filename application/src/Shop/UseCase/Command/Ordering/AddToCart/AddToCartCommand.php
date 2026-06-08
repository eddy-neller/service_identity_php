<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\AddToCart;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class AddToCartCommand implements CommandInterface
{
    public function __construct(
        public CustomerId $customerId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
