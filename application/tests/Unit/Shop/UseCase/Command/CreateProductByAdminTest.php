<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin\CreateProductByAdminCommand;
use App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin\CreateProductByAdminCommandHandler;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateProductByAdminTest extends TestCase
{
    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string CATEGORY_ID = '550e8400-e29b-41d4-a716-446655440001';

    private ProductRepositoryInterface&MockObject $productRepository;

    private CategoryRepositoryInterface&MockObject $categoryRepository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private SlugGeneratorInterface&MockObject $slugGenerator;

    private CreateProductByAdminCommandHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->slugGenerator = $this->createMock(SlugGeneratorInterface::class);
        $this->handler = new CreateProductByAdminCommandHandler(
            $this->productRepository,
            $this->categoryRepository,
            $this->clock,
            $this->transactional,
            $this->slugGenerator,
        );
    }

    public function testHandleCreatesProductAndUpdatesCategory(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $category = $this->createCategory($categoryId);
        $slug = Slug::fromString('new-product');

        $command = new CreateProductByAdminCommand(
            title: 'New product',
            subtitle: 'Product subtitle',
            description: 'Product description',
            price: 12.5,
            categoryId: $categoryId,
        );

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->productRepository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($productId);

        $this->slugGenerator->expects($this->once())
            ->method('generate')
            ->with('New product')
            ->willReturn($slug);

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn($category);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $product) use ($productId, $categoryId, $slug, $now): bool {
                return $product->getId()->equals($productId)
                    && 'New product' === $product->getTitle()->toString()
                    && 'Product subtitle' === $product->getSubtitle()->toString()
                    && 'Product description' === $product->getDescription()->toString()
                    && $product->getPrice()->equals(Money::fromInt(1250))
                    && $product->getSlug()->equals($slug)
                    && $product->getCategoryId()->equals($categoryId)
                    && $product->getCreatedAt() === $now
                    && $product->getUpdatedAt() === $now;
            }));

        $this->categoryRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Category $savedCategory) use ($category, $now): bool {
                return $savedCategory === $category
                    && 1 === $savedCategory->getProductCount()
                    && $savedCategory->getUpdatedAt() === $now;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertSame($category, $output->productItem->category);
        $this->assertSame('New product', $output->productItem->product->getTitle()->toString());
    }

    public function testHandleThrowsWhenCategoryNotFound(): void
    {
        $now = new DateTimeImmutable('2024-01-01 00:00:00');
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);

        $command = new CreateProductByAdminCommand(
            title: 'New product',
            subtitle: 'Product subtitle',
            description: 'Product description',
            price: 10.0,
            categoryId: $categoryId,
        );

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->productRepository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($productId);

        $this->slugGenerator->expects($this->once())
            ->method('generate')
            ->with('New product')
            ->willReturn(Slug::fromString('new-product'));

        $this->categoryRepository->expects($this->once())
            ->method('findById')
            ->with($categoryId)
            ->willReturn(null);

        $this->productRepository->expects($this->never())
            ->method('save');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        $this->handler->handle($command);
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
