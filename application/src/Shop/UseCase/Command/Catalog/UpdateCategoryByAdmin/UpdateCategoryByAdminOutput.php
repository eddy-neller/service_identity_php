<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateCategoryByAdmin;

use App\Application\Shop\ReadModel\Catalog\CategoryItem;

final readonly class UpdateCategoryByAdminOutput
{
    public function __construct(
        public CategoryItem $categoryItem,
    ) {
    }
}
