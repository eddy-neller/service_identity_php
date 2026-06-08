<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query;

use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Application\Shop\UseCase\Query\Catalog\DisplayCategory\DisplayCategoryQuery;
use App\Application\Shop\UseCase\Query\Catalog\DisplayCategory\DisplayCategoryQueryHandler;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayCategoryTest extends TestCase
{
    private const string CATEGORY_ID = '550e8400-e29b-41d4-a716-446655440000';

    private CategoryRepositoryInterface&MockObject $repository;

    private DisplayCategoryQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CategoryRepositoryInterface::class);
        $this->handler = new DisplayCategoryQueryHandler($this->repository);
    }

    public function testHandleReturnsCategoryTreeWhenFound(): void
    {
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $query = new DisplayCategoryQuery($categoryId);
        $category = $this->createCategory($categoryId);
        $categoryItem = new CategoryItem($category, null, []);

        $this->repository->expects($this->once())
            ->method('findItemById')
            ->with($categoryId)
            ->willReturn($categoryItem);

        $output = $this->handler->handle($query);

        $this->assertSame($categoryItem, $output->categoryItem);
    }

    public function testHandleThrowsWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $query = new DisplayCategoryQuery($categoryId);

        $this->repository->expects($this->once())
            ->method('findItemById')
            ->with($categoryId)
            ->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        $this->handler->handle($query);
    }

    private function createCategory(CategoryId $id): Category
    {
        return Category::create(
            id: $id,
            title: CategoryTitle::fromString('My category'),
            slug: Slug::fromString('my-category'),
            now: new DateTimeImmutable('2024-01-01 09:00:00'),
        );
    }
}
