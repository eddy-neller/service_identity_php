<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Application\Shared\Port\FileInterface;
use App\Application\Shop\ReadModel\Catalog\ProductList;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;

interface ProductRepositoryInterface
{
    public const array SORT_FIELDS = ['title', 'category.title', 'price', 'createdAt'];

    public function nextIdentity(): ProductId;

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): ProductList;

    public function save(Product $product): void;

    public function delete(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function findByTitle(ProductTitle $title): ?Product;

    /**
     * @param ProductId[] $ids
     *
     * @return Product[]
     */
    public function findByIds(array $ids): array;

    public function updateImage(ProductId $id, FileInterface $file): ?Product;
}
