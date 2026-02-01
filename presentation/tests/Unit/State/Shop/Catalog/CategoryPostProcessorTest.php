<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Catalog;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\ReadModel\CategoryItem;
use App\Application\Shop\UseCase\Command\Catalog\CreateCategoryByAdmin\CreateCategoryByAdminCommand;
use App\Application\Shop\UseCase\Command\Catalog\CreateCategoryByAdmin\CreateCategoryByAdminOutput;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\Dto\Catalog\Category\CategoryPostInput;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use App\Presentation\Shop\State\Catalog\Category\CategoryPostProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CategoryPostProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private CategoryPostProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $presenter = new CategoryResourcePresenter();

        $this->processor = new CategoryPostProcessor(
            $this->commandBus,
            $presenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = new CategoryPostInput();
        $input->title = 'New category';
        $input->description = 'Category description';
        $input->parent = $this->createCategoryResource('550e8400-e29b-41d4-a716-446655440001');

        $output = new CreateCategoryByAdminOutput($this->createCategoryTree());

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($input, $output): CreateCategoryByAdminOutput {
                $this->assertInstanceOf(CreateCategoryByAdminCommand::class, $command);
                $this->assertSame($input->title, $command->title);
                $this->assertSame($input->description, $command->description);
                $this->assertTrue($command->parentId->equals(CategoryId::fromString($input->parent->id)));

                return $output;
            });

        $result = $this->processor->process($input, $this->operation);

        $this->assertInstanceOf(CategoryResource::class, $result);
        $this->assertSame('New category', $result->title);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($invalidInput, $this->operation);
    }

    private function createCategoryTree(): CategoryItem
    {
        $categoryId = CategoryId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $parentId = CategoryId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $now = new DateTimeImmutable('2024-01-01 10:00:00');

        $category = Category::create(
            id: $categoryId,
            title: CategoryTitle::fromString('New category'),
            slug: Slug::fromString('new-category'),
            now: $now,
            parentId: $parentId,
        );

        $parent = Category::create(
            id: $parentId,
            title: CategoryTitle::fromString('Parent category'),
            slug: Slug::fromString('parent-category'),
            now: $now,
        );

        return new CategoryItem($category, $parent, []);
    }

    private function createCategoryResource(string $id): CategoryResource
    {
        $resource = new CategoryResource();
        $resource->id = $id;

        return $resource;
    }
}
