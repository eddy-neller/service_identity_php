<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Catalog\Product;

use ApiPlatform\Metadata\GetCollection;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Application\Shop\UseCase\Query\Catalog\DisplayListProduct\DisplayListProductOutput;
use App\Application\Shop\UseCase\Query\Catalog\DisplayListProduct\DisplayListProductQuery;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Category as DomainCategory;
use App\Domain\Shop\Catalog\Model\Product as DomainProduct;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductImage;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use App\Presentation\Shop\State\Catalog\Product\ProductCollectionProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProductCollectionProviderTest extends TestCase
{
    public function testItMapsProductsToResourcesAndSetsPagination(): void
    {
        $request = new Request();
        $queryBus = $this->createMock(QueryBusInterface::class);
        $category = $this->createCategory();
        $product = $this->createProduct($category->getId());
        $output = new DisplayListProductOutput([
            new ProductItem($product, $category),
        ], 5, 3);

        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($output): DisplayListProductOutput {
                $this->assertInstanceOf(DisplayListProductQuery::class, $query);
                $this->assertSame(2, $query->pagination->page);
                $this->assertSame(15, $query->pagination->itemsPerPage);
                $this->assertSame('Product', $query->filters['title'] ?? null);
                $this->assertSame('Subtitle', $query->filters['subtitle'] ?? null);
                $this->assertSame('Nice', $query->filters['description'] ?? null);
                $this->assertSame('/api/shop/categories/550e8400-e29b-41d4-a716-446655440000', $query->filters['category'] ?? null);
                $this->assertSame(['createdAt' => 'asc'], $query->orderBy);

                return $output;
            });

        $imageUrlResolver = $this->createMock(ProductImageUrlResolverInterface::class);
        $imageUrlResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('product.jpg')
            ->willReturn('/uploads/product.jpg');

        $provider = new ProductCollectionProvider(
            queryBus: $queryBus,
            productResourcePresenter: new ProductResourcePresenter(
                $imageUrlResolver,
                new CategoryResourcePresenter(),
            ),
        );

        $result = $provider->provide(
            new GetCollection(name: 'shop-products-col'),
            context: [
                'request' => $request,
                'filters' => [
                    'page' => '2',
                    'itemsPerPage' => '15',
                    'title' => 'Product',
                    'subtitle' => 'Subtitle',
                    'description' => 'Nice',
                    'category' => '/api/shop/categories/550e8400-e29b-41d4-a716-446655440000',
                    'order' => [
                        'createdAt' => 'asc',
                    ],
                ],
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductResource::class, $result[0]);
        $this->assertSame('Product title', $result[0]->title);
        $this->assertSame('/uploads/product.jpg', $result[0]->imageUrl);
        $this->assertSame('Category title', $result[0]->category->title);
        $this->assertSame(5, $request->attributes->get('_total_items'));
        $this->assertSame(3, $request->attributes->get('_total_pages'));
    }

    public function testItHandlesInvalidFiltersWithoutRequest(): void
    {
        $queryBus = $this->createMock(QueryBusInterface::class);
        $category = $this->createCategory();
        $product = $this->createProduct($category->getId());
        $output = new DisplayListProductOutput([
            new ProductItem($product, $category),
        ], 1, 1);

        $queryBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($output): DisplayListProductOutput {
                $this->assertInstanceOf(DisplayListProductQuery::class, $query);
                $this->assertSame(1, $query->pagination->page);
                $this->assertSame(30, $query->pagination->itemsPerPage);
                $this->assertSame([], $query->filters);
                $this->assertSame([], $query->orderBy);

                return $output;
            });

        $imageUrlResolver = $this->createMock(ProductImageUrlResolverInterface::class);
        $imageUrlResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('product.jpg')
            ->willReturn('/uploads/product.jpg');

        $provider = new ProductCollectionProvider(
            queryBus: $queryBus,
            productResourcePresenter: new ProductResourcePresenter(
                $imageUrlResolver,
                new CategoryResourcePresenter(),
            ),
        );

        $result = $provider->provide(
            new GetCollection(name: 'shop-products-col'),
            context: [
                'filters' => 'not-an-array',
            ],
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ProductResource::class, $result[0]);
    }

    private function createCategory(): DomainCategory
    {
        return DomainCategory::reconstitute(
            id: CategoryId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            title: CategoryTitle::fromString('Category title'),
            slug: Slug::fromString('category-title'),
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2025-02-01 10:00:00'),
            parentId: null,
            description: null,
            productCount: 2,
            level: 1,
        );
    }

    private function createProduct(CategoryId $categoryId): DomainProduct
    {
        $product = DomainProduct::create(
            id: ProductId::fromString('1d2f4c1a-2b2b-4aa2-9a20-8b3e18f1d152'),
            title: ProductTitle::fromString('Product title'),
            subtitle: ProductSubtitle::fromString('Product subtitle'),
            description: ProductDescription::fromString('Nice product'),
            price: Money::fromInt(1999),
            slug: Slug::fromString('product-title'),
            categoryId: $categoryId,
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
        );

        $product->updateImage(new ProductImage('product.jpg'), new DateTimeImmutable('2025-01-02 10:00:00'));

        return $product;
    }
}
