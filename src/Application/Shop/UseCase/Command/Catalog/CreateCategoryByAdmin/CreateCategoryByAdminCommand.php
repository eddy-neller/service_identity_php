<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateCategoryByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class CreateCategoryByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $parentId,
    ) {
    }
}
