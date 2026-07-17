<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\DeleteCategoryByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class DeleteCategoryByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $categoryId,
    ) {
    }
}
