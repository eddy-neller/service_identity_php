<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query;

use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Application\Shop\ReadModel\Catalog\ProductList;
use App\Application\Shop\UseCase\Query\Catalog\DisplayListProduct\DisplayListProductQuery;
use App\Application\Shop\UseCase\Query\Catalog\DisplayListProduct\DisplayListProductQueryHandler;
use App\Domain\SharedKernel\ValueObject\Slug;
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

final class DisplayListProductTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $repository;

    private DisplayListProductQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ProductRepositoryInterface::class);
        $this->handler = new DisplayListProductQueryHandler($this->repository);
    }

    public function testHandleReturnsProductsAndPagination(): void
    {
        $query = new DisplayListProductQuery(
            pagination: Pagination::fromValues(2, 5),
            filters: [
                'title' => 'Product',
                'subtitle' => 'Subtitle',
            ],
            orderBy: ['title' => 'ASC'],
        );

        $categoryId = CategoryId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $item = new ProductItem(
            product: $this->createProduct(ProductId::fromString('1d2f4c1a-2b2b-4aa2-9a20-8b3e18f1d152'), $categoryId),
            category: $this->createCategory($categoryId),
        );
        $list = new ProductList([$item], 10, 2);

        $this->repository->expects($this->once())
            ->method('list')
            ->with(['title' => 'Product', 'subtitle' => 'Subtitle'], ['title' => 'ASC'], 2, 5)
            ->willReturn($list);

        $output = $this->handler->handle($query);

        $this->assertSame([$item], $output->products);
        $this->assertSame(10, $output->totalItems);
        $this->assertSame(2, $output->totalPages);
    }

    public function testHandleAppliesDefaultsWhenValuesAreInvalid(): void
    {
        $query = new DisplayListProductQuery(
            pagination: Pagination::fromValues(0, 0),
            filters: [],
            orderBy: [],
        );

        $categoryId = CategoryId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $item = new ProductItem(
            product: $this->createProduct(ProductId::fromString('2d2f4c1a-2b2b-4aa2-9a20-8b3e18f1d153'), $categoryId),
            category: $this->createCategory($categoryId),
        );
        $list = new ProductList([$item], 1, 1);

        $this->repository->expects($this->once())
            ->method('list')
            ->with([], ['createdAt' => 'DESC'], 1, 30)
            ->willReturn($list);

        $output = $this->handler->handle($query);

        $this->assertSame([$item], $output->products);
    }

    public function testQueryCacheKeyIsStableWhenFiltersAndOrderByAreReordered(): void
    {
        $this->repository->expects($this->never())->method('list');

        $queryA = new DisplayListProductQuery(
            pagination: Pagination::fromValues(2, 5),
            filters: ['title' => 'Product', 'subtitle' => 'Sub'],
            orderBy: ['title' => 'ASC', 'createdAt' => 'DESC'],
        );
        $queryB = new DisplayListProductQuery(
            pagination: Pagination::fromValues(2, 5),
            filters: ['subtitle' => 'Sub', 'title' => 'Product'],
            orderBy: ['createdAt' => 'DESC', 'title' => 'ASC'],
        );

        $this->assertSame($queryA->cacheKey(), $queryB->cacheKey());
    }

    public function testQueryCacheMetadata(): void
    {
        $this->repository->expects($this->never())->method('list');

        $query = new DisplayListProductQuery(
            pagination: Pagination::fromValues(1, 10),
        );

        $this->assertSame(3600, $query->cacheTtl());
        $this->assertSame(['products-collection'], $query->cacheTags());
    }

    private function createCategory(CategoryId $categoryId): Category
    {
        return Category::reconstitute(
            id: $categoryId,
            title: CategoryTitle::fromString('Category title'),
            slug: Slug::fromString('category-title'),
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2025-01-02 10:00:00'),
            parentId: null,
            description: null,
            productCount: 0,
            level: 1,
        );
    }

    private function createProduct(ProductId $productId, CategoryId $categoryId): Product
    {
        return Product::create(
            id: $productId,
            title: ProductTitle::fromString('Product title'),
            subtitle: ProductSubtitle::fromString('Product subtitle'),
            description: ProductDescription::fromString('Product description'),
            price: Money::fromInt(1999),
            slug: Slug::fromString('product-title'),
            categoryId: $categoryId,
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
        );
    }
}
