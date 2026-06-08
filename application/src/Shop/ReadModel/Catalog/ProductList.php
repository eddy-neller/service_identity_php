<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Catalog;

final readonly class ProductList
{
    public function __construct(
        public array $products,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
