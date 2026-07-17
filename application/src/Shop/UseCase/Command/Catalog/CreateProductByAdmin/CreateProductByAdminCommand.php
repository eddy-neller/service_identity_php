<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class CreateProductByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $title,
        public string $subtitle,
        public string $description,
        public float $price,
        public string $categoryId,
    ) {
    }
}
