<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Catalog;

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
        $query = new DisplayCategoryQuery($categoryId->toString());
        $category = $this->createCategory($categoryId);
        $categoryItem = CategoryItem::fromCategory($category, null, []);

        $this->repository->expects($this->once())
            ->method('findTreeById')
            ->with($categoryId)
            ->willReturn(['category' => $category, 'parent' => null, 'children' => []]);

        $output = $this->handler->handle($query);

        $this->assertEquals($categoryItem, $output);
    }

    public function testHandleThrowsWhenCategoryNotFound(): void
    {
        $categoryId = CategoryId::fromString(self::CATEGORY_ID);
        $query = new DisplayCategoryQuery($categoryId->toString());

        $this->repository->expects($this->once())
            ->method('findTreeById')
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
