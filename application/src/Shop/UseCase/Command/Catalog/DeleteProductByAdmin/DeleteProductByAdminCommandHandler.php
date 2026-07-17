<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\DeleteProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\ValueObject\ProductId;

final readonly class DeleteProductByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(DeleteProductByAdminCommand $command): void
    {
        $productId = ProductId::fromString($command->productId);

        $this->transactional->transactional(function () use ($productId): void {
            $product = $this->productRepository->findById($productId);

            if (null === $product) {
                throw new ProductNotFoundException();
            }

            $category = $this->categoryRepository->findById($product->getCategoryId());

            if (null === $category) {
                throw new CategoryNotFoundException();
            }

            $now = $this->clock->now();

            $product->delete($now);
            $category->decreaseProductCount($now);

            $this->categoryRepository->save($category);

            $this->productRepository->delete($product);
        });
    }
}
