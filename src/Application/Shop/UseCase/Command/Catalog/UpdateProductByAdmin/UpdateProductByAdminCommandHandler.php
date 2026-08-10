<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductTitleAlreadyUsedException;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;

final readonly class UpdateProductByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function handle(UpdateProductByAdminCommand $command): ProductItem
    {
        $productId = ProductId::fromString($command->productId);
        $title = null !== $command->title ? ProductTitle::fromString($command->title) : null;
        $subtitle = null !== $command->subtitle ? ProductSubtitle::fromString($command->subtitle) : null;
        $description = null !== $command->description
            ? ProductDescription::fromString($command->description)
            : null;
        $price = null !== $command->price ? Money::fromEuros($command->price) : null;
        $categoryId = null !== $command->categoryId ? CategoryId::fromString($command->categoryId) : null;
        $slug = null !== $title ? $this->slugGenerator->generate($title->toString()) : null;

        return $this->transactional->transactional(function () use ($productId, $title, $subtitle, $description, $price, $categoryId, $slug): ProductItem {
            $product = $this->productRepository->findById($productId);

            if (null === $product) {
                throw new ProductNotFoundException();
            }

            $now = $this->clock->now();

            $this->applyTitleAndSubtitle($title, $subtitle, $slug, $product, $now);

            if (null !== $description) {
                $product->rewrite($description, $now);
            }

            if (null !== $price) {
                $product->reprice($price, $now);
            }

            $this->applyCategoryChange($categoryId, $product, $now);

            $this->productRepository->save($product);

            $category = $this->categoryRepository->findById($product->getCategoryId());
            if (null === $category) {
                throw new CategoryNotFoundException();
            }

            return ProductItem::fromProduct($product, $category);
        });
    }

    private function applyTitleAndSubtitle(
        ?ProductTitle $title,
        ?ProductSubtitle $subtitle,
        ?Slug $slug,
        Product $product,
        DateTimeImmutable $now,
    ): void {
        if (null === $title && null === $subtitle) {
            return;
        }

        $newTitle = $title ?? $product->getTitle();

        if (null !== $title) {
            $existing = $this->productRepository->findByTitle($newTitle);
            if (null !== $existing && !$existing->getId()->equals($product->getId())) {
                throw new ProductTitleAlreadyUsedException();
            }
        }

        $newSubtitle = $subtitle ?? $product->getSubtitle();

        $product->rename($newTitle, $newSubtitle, $now);

        if (null !== $title && null !== $slug) {
            $product->reSlug($slug, $now);
        }
    }

    private function applyCategoryChange(
        ?CategoryId $categoryId,
        Product $product,
        DateTimeImmutable $now,
    ): void {
        if (null === $categoryId || $categoryId->equals($product->getCategoryId())) {
            return;
        }

        $oldCategory = $this->categoryRepository->findById($product->getCategoryId());
        $newCategory = $this->categoryRepository->findById($categoryId);

        if (null === $oldCategory) {
            throw new CategoryNotFoundException('Current category not found.');
        }

        if (null === $newCategory) {
            throw new CategoryNotFoundException('New category not found.');
        }

        $product->moveToCategory($categoryId, $now);

        $oldCategory->decreaseProductCount($now);
        $this->categoryRepository->save($oldCategory);
        $newCategory->increaseProductCount($now);
        $this->categoryRepository->save($newCategory);
    }
}
