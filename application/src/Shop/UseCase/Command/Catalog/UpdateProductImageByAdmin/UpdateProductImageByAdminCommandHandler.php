<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductImageByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Domain\Shop\Catalog\Exception\CatalogDomainException;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;

final readonly class UpdateProductImageByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateProductImageByAdminCommand $command): ProductItem
    {
        if (!$command->imageFile->isValid()) {
            throw new CatalogDomainException('Invalid image file.', 400);
        }

        return $this->transactional->transactional(function () use ($command): ProductItem {
            $product = $this->productRepository->updateImage($command->productId, $command->imageFile);

            if (null === $product) {
                throw new ProductNotFoundException();
            }

            $category = $this->categoryRepository->findById($product->getCategoryId());
            if (null === $category) {
                throw new CategoryNotFoundException();
            }

            return ProductItem::fromProduct($product, $category);
        });
    }
}
