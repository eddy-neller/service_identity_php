<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\DeleteProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class DeleteProductByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $productId,
    ) {
    }
}
