<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Catalog;

final readonly class CategoryList
{
    public function __construct(
        public array $categories,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
