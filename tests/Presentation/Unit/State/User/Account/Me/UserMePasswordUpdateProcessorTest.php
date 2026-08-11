<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\User\Account\Me;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Account\UpdatePassword\UpdatePasswordCommand;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\Account\Me\UserMePasswordUpdateInput;
use App\Presentation\User\State\Account\Me\UserMePasswordUpdateProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;

final class UserMePasswordUpdateProcessorTest extends TestCase
{
    private Security&MockObject $security;

    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private User&MockObject $user;

    private UserMePasswordUpdateProcessor $processor;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');
        $this->user = $this->createMock(User::class);

        $this->processor = new UserMePasswordUpdateProcessor(
            $this->security,
            $this->commandBus,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserMePasswordUpdateInput();
        $userId = Uuid::uuid4();

        $this->user->expects($this->once())
            ->method('getId')
            ->willReturn($userId);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($input) {
                $this->assertInstanceOf(UpdatePasswordCommand::class, $command);
                $this->assertSame($input->currentPassword, $command->currentPassword);
                $this->assertSame($input->newPassword, $command->newPassword);

                return true;
            }));

        $this->processor->process($input, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($invalidInput, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process('invalid', $this->operation);
    }

    public function testProcessThrowsLogicExceptionForArrayInput(): void
    {
        $this->security->expects($this->never())
            ->method('getUser');
        $this->user->expects($this->never())
            ->method('getId');
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(['newPassword' => 'test'], $this->operation);
    }

    private function createValidUserMePasswordUpdateInput(): UserMePasswordUpdateInput
    {
        $input = new UserMePasswordUpdateInput();
        $input->currentPassword = 'current_password';
        $input->newPassword = 'NewPassword123!';

        return $input;
    }
}
