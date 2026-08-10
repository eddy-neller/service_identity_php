<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\ClearCart;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class ClearCartCommand implements CommandInterface
{
    public function __construct(
        public string $customerId,
    ) {
    }
}
