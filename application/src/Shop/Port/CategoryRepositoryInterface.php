<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Application\Shop\ReadModel\Catalog\CategoryList;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;

interface CategoryRepositoryInterface
{
    public function nextIdentity(): CategoryId;

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): CategoryList;

    public function save(Category $category): void;

    public function delete(Category $category): void;

    public function findById(CategoryId $id): ?Category;

    public function findItemById(CategoryId $id): ?CategoryItem;
}
