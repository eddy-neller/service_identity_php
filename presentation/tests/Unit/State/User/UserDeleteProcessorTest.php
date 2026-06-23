<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\DeleteUserByAdmin\DeleteUserByAdminCommand;
use App\Domain\User\ValueObject\UserId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\State\UserDeleteProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserDeleteProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private UserDeleteProcessor $userDeleteProcessor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $this->userDeleteProcessor = new UserDeleteProcessor(
            $this->commandBus,
        );
    }

    public function testProcessWithValidIdCallsDeleteUserByAdmin(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $userIdVO = UserId::fromString($userId);
        $uriVariables = ['id' => $userId];

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($userIdVO) {
                $this->assertInstanceOf(DeleteUserByAdminCommand::class, $command);
                $this->assertTrue($command->userId->equals($userIdVO));

                return true;
            }));

        $this->userDeleteProcessor->process(null, $this->operation, $uriVariables);
    }

    public function testProcessThrowsLogicExceptionWhenIdIsMissing(): void
    {
        $uriVariables = [];

        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->userDeleteProcessor->process(null, $this->operation, $uriVariables);
    }

    public function testProcessReturnsNullAfterSuccessfulDeletion(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $userIdVO = UserId::fromString($userId);
        $uriVariables = ['id' => $userId];

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($userIdVO) {
                $this->assertInstanceOf(DeleteUserByAdminCommand::class, $command);
                $this->assertTrue($command->userId->equals($userIdVO));

                return true;
            }));

        $this->userDeleteProcessor->process(null, $this->operation, $uriVariables);
    }

    public function testProcessWithDifferentUserId(): void
    {
        $userId = '660e8400-e29b-41d4-a716-446655440001';
        $userIdVO = UserId::fromString($userId);
        $uriVariables = ['id' => $userId];

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($userIdVO) {
                $this->assertInstanceOf(DeleteUserByAdminCommand::class, $command);
                $this->assertTrue($command->userId->equals($userIdVO));

                return true;
            }));

        $this->userDeleteProcessor->process(null, $this->operation, $uriVariables);
    }
}
