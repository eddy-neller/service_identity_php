<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayProduct;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;

final readonly class DisplayProductQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function handle(DisplayProductQuery $query): DisplayProductOutput
    {
        $product = $this->productRepository->findById($query->productId);

        if (null === $product) {
            throw new ProductNotFoundException();
        }

        $category = $this->categoryRepository->findById($product->getCategoryId());
        if (null === $category) {
            throw new CategoryNotFoundException();
        }

        return new DisplayProductOutput(new ProductItem($product, $category));
    }
}
