<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Catalog\Category;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Application\Shop\UseCase\Query\Catalog\DisplayCategory\DisplayCategoryOutput;
use App\Application\Shop\UseCase\Query\Catalog\DisplayCategory\DisplayCategoryQuery;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use App\Presentation\Shop\State\Catalog\Category\CategoryGetProvider;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CategoryGetProviderTest extends TestCase
{
    private QueryBusInterface&MockObject $queryBus;

    private Operation&MockObject $operation;

    private CategoryGetProvider $provider;

    protected function setUp(): void
    {
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $presenter = new CategoryResourcePresenter();

        $this->provider = new CategoryGetProvider($this->queryBus, $presenter);
    }

    public function testProvideWithValidId(): void
    {
        $categoryId = '550e8400-e29b-41d4-a716-446655440000';
        $output = new DisplayCategoryOutput($this->createCategoryTree());

        $this->queryBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($query) use ($categoryId, $output): DisplayCategoryOutput {
                $this->assertInstanceOf(DisplayCategoryQuery::class, $query);
                $this->assertSame($categoryId, $query->categoryId->toString());

                return $output;
            });

        $result = $this->provider->provide($this->operation, ['id' => $categoryId]);

        $this->assertInstanceOf(CategoryResource::class, $result);
        $this->assertSame('Category title', $result->title);
        $this->assertFalse($result->hasChildren);
    }

    public function testProvideThrowsLogicExceptionWhenIdIsMissing(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->provider->provide($this->operation, []);
    }

    public function testProvideThrowsLogicExceptionWhenIdIsNotString(): void
    {
        $this->queryBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->provider->provide($this->operation, ['id' => 123]);
    }

    private function createCategoryTree(): CategoryItem
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');
        $category = Category::create(
            id: CategoryId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            title: CategoryTitle::fromString('Category title'),
            slug: Slug::fromString('category-title'),
            now: $now,
        );

        return new CategoryItem($category, null, []);
    }
}
