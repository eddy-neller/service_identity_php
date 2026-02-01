<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query;

use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\UseCase\Query\Catalog\DisplayProduct\DisplayProductQuery;
use App\Application\Shop\UseCase\Query\Catalog\DisplayProduct\DisplayProductQueryHandler;
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
use PHPUnit\Framework\TestCase;

final class DisplayProductTest extends TestCase
{
    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string CATEGORY_ID = '550e8400-e29b-41d4-a716-446655440001';

    private ProductRepositoryInterface $productRepository;

    private CategoryRepositoryInterface $categoryRepository;

    private DisplayProductQueryHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->handler = new DisplayProductQueryHandler(
            $this->productRepository,
            $this->categoryRepository,
        );
    }

    public function testHandleReturnsProductViewWhenFound(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $product = $this->createProduct($productId, $categoryId);
        $category = $this->createCategory($categoryId);
        $query = new DisplayProductQuery($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $output = $this->handler->handle($query);

        $this->assertSame($product, $output->productItem->product);
        $this->assertSame($category, $output->productItem->category);
    }

    public function testHandleThrowsWhenProductNotFound(): void
    {
        $this->categoryRepository->expects($this->never())
            ->method('findById');

        $productId = ProductId::fromString(self::PRODUCT_ID);
        $query = new DisplayProductQuery($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn(null);

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product not found.');

        $this->handler->handle($query);
    }

    public function testHandleThrowsWhenCategoryNotFound(): void
    {
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $product = $this->createProduct($productId, $categoryId);
        $query = new DisplayProductQuery($productId);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        $this->handler->handle($query);
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
