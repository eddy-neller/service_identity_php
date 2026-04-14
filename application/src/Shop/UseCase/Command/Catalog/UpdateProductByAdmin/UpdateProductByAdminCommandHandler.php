<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\ProductItem;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;

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

    public function handle(UpdateProductByAdminCommand $command): UpdateProductByAdminOutput
    {
        $product = $this->productRepository->findById($command->productId);

        if (null === $product) {
            throw new ProductNotFoundException();
        }

        // Product uses explicit domain methods (rename/reprice/...) to keep invariants clear
        // instead of a generic update() like Address.
        return $this->transactional->transactional(function () use ($command, $product): UpdateProductByAdminOutput {
            $now = $this->clock->now();

            if (null !== $command->title || null !== $command->subtitle) {
                $newTitle = null !== $command->title
                    ? ProductTitle::fromString($command->title)
                    : $product->getTitle();
                $newSubtitle = null !== $command->subtitle
                    ? ProductSubtitle::fromString($command->subtitle)
                    : $product->getSubtitle();

                $product->rename($newTitle, $newSubtitle, $now);

                if (null !== $command->title) {
                    $product->reSlug($this->slugGenerator->generate($newTitle->toString()), $now);
                }
            }

            if (null !== $command->description) {
                $product->rewrite(ProductDescription::fromString($command->description), $now);
            }

            if (null !== $command->price) {
                $product->reprice(Money::fromInt((int) round($command->price * 100)), $now);
            }

            if (null !== $command->categoryId && !$command->categoryId->equals($product->getCategoryId())) {
                $oldCategory = $this->categoryRepository->findById($product->getCategoryId());
                $newCategory = $this->categoryRepository->findById($command->categoryId);

                if (null === $oldCategory) {
                    throw new CategoryNotFoundException('Current category not found.');
                }

                if (null === $newCategory) {
                    throw new CategoryNotFoundException('New category not found.');
                }

                $product->moveToCategory($command->categoryId, $now);

                $oldCategory->decreaseProductCount($now);
                $this->categoryRepository->save($oldCategory);
                $newCategory->increaseProductCount($now);
                $this->categoryRepository->save($newCategory);
            }

            $this->productRepository->save($product);

            $category = $this->categoryRepository->findById($product->getCategoryId());
            if (null === $category) {
                throw new CategoryNotFoundException();
            }

            return new UpdateProductByAdminOutput(new ProductItem($product, $category));
        });
    }
}
