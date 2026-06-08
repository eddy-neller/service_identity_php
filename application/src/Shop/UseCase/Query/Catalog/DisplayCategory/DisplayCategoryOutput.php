<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayCategory;

use App\Application\Shop\ReadModel\Catalog\CategoryItem;

final readonly class DisplayCategoryOutput
{
    public function __construct(
        public CategoryItem $categoryItem,
    ) {
    }
}
