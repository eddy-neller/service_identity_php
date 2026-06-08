<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Catalog;

use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\Model\Product;

final readonly class ProductItem
{
    public function __construct(
        public Product $product,
        public Category $category,
    ) {
    }
}
