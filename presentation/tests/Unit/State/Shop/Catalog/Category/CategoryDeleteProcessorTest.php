<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Catalog\Category;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\DeleteCategoryByAdmin\DeleteCategoryByAdminCommand;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\State\Catalog\Category\CategoryDeleteProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CategoryDeleteProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private CategoryDeleteProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $this->processor = new CategoryDeleteProcessor($this->commandBus);
    }

    public function testProcessWithValidIdDispatchesCommand(): void
    {
        $categoryId = '550e8400-e29b-41d4-a716-446655440000';
        $categoryIdVo = CategoryId::fromString($categoryId);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($categoryIdVo): bool {
                $this->assertInstanceOf(DeleteCategoryByAdminCommand::class, $command);
                $this->assertSame($categoryIdVo->toString(), $command->categoryId);

                return true;
            }));

        $result = $this->processor->process(null, $this->operation, ['id' => $categoryId]);

        $this->assertNull($result);
    }

    public function testProcessThrowsLogicExceptionWhenIdIsMissing(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(null, $this->operation, []);
    }

    public function testProcessThrowsLogicExceptionWhenIdIsNotString(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(null, $this->operation, ['id' => 123]);
    }
}
