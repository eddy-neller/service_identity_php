<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Catalog;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\UseCase\Command\Catalog\DeleteProductByAdmin\DeleteProductByAdminCommand;
use App\Application\Shop\UseCase\Command\Catalog\DeleteProductByAdmin\DeleteProductByAdminCommandHandler;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteProductByAdminTest extends TestCase
{
    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string CATEGORY_ID = '550e8400-e29b-41d4-a716-446655440001';

    private ProductRepositoryInterface&MockObject $productRepository;

    private CategoryRepositoryInterface&MockObject $categoryRepository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private DeleteProductByAdminCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new DeleteProductByAdminCommandHandler(
            $this->productRepository,
            $this->categoryRepository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleDeletesProductAndUpdatesCategory(): void
    {
        $now = new DateTimeImmutable('2024-03-01 10:00:00');
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $product = $this->createProduct($productId, $categoryId);
        $category = $this->createCategory($categoryId);
        $category->increaseProductCount(new DateTimeImmutable('2024-02-01 10:00:00'));

        $command = new DeleteProductByAdminCommand($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $this->categoryRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Category $savedCategory) use ($category, $now): bool {
                return $savedCategory === $category
                    && 0 === $savedCategory->getProductCount()
                    && $savedCategory->getUpdatedAt() === $now;
            }));

        $this->productRepository->expects($this->once())
            ->method('delete')
            ->with($product);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                $callback();
            });

        $this->handler->handle($command);

        $this->assertSame($now, $product->getUpdatedAt());
    }

    public function testHandleThrowsWhenProductNotFound(): void
    {
        $this->categoryRepository->expects($this->never())
            ->method('findById');
        $this->clock->expects($this->never())
            ->method('now');
        $this->transactional->expects($this->never())
            ->method('transactional');

        $productId = ProductId::fromString(self::PRODUCT_ID);
        $command = new DeleteProductByAdminCommand($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn(null);

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product not found.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsWhenCategoryNotFound(): void
    {
        $now = new DateTimeImmutable('2024-03-01 10:00:00');
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $product = $this->createProduct($productId, $categoryId);

        $command = new DeleteProductByAdminCommand($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                $callback();
            });

        $this->handler->handle($command);
    }

    private function createProduct(ProductId $productId, CategoryId $categoryId): Product
    {
        return Product::create(
            id: $productId,
            title: ProductTitle::fromString('Product title'),
            subtitle: ProductSubtitle::fromString('Product subtitle'),
            description: ProductDescription::fromString('Product description'),
            price: Money::fromInt(1299),
            slug: Slug::fromString('product-title'),
            categoryId: $categoryId,
            now: new DateTimeImmutable('2024-01-01 09:00:00'),
        );
    }

    private function createCategory(CategoryId $categoryId): Category
    {
        return Category::create(
            id: $categoryId,
            title: CategoryTitle::fromString('Category title'),
            slug: Slug::fromString('category-title'),
            now: new DateTimeImmutable('2024-01-01 09:00:00'),
        );
    }
}
