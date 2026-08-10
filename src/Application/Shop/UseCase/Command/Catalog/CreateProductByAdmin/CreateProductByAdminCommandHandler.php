<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductTitleAlreadyUsedException;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;

final readonly class CreateProductByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function handle(CreateProductByAdminCommand $command): ProductItem
    {
        $id = $this->productRepository->nextIdentity();
        $title = ProductTitle::fromString($command->title);
        $subtitle = ProductSubtitle::fromString($command->subtitle);
        $description = ProductDescription::fromString($command->description);
        $price = Money::fromEuros($command->price);
        $slug = $this->slugGenerator->generate($title->toString());
        $categoryId = CategoryId::fromString($command->categoryId);

        return $this->transactional->transactional(function () use ($id, $title, $subtitle, $description, $price, $slug, $categoryId): ProductItem {
            if (null !== $this->productRepository->findByTitle($title)) {
                throw new ProductTitleAlreadyUsedException();
            }

            $category = $this->categoryRepository->findById($categoryId);
            if (null === $category) {
                throw new CategoryNotFoundException();
            }

            $now = $this->clock->now();

            $product = Product::create(
                id: $id,
                title: $title,
                subtitle: $subtitle,
                description: $description,
                price: $price,
                slug: $slug,
                categoryId: $categoryId,
                now: $now,
            );

            $this->productRepository->save($product);

            $category->increaseProductCount($now);
            $this->categoryRepository->save($category);

            return ProductItem::fromProduct($product, $category);
        });
    }
}
