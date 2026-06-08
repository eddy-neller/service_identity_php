<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Catalog;

use App\Domain\Shop\Catalog\Model\Category;

final readonly class CategoryItem
{
    public function __construct(
        public Category $category,
        public ?Category $parent,
        public ?array $children,
    ) {
    }
}
