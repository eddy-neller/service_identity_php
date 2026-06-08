<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateCategoryByAdmin;

use App\Application\Shop\ReadModel\Catalog\CategoryItem;

final readonly class CreateCategoryByAdminOutput
{
    public function __construct(
        public CategoryItem $categoryItem,
    ) {
    }
}
