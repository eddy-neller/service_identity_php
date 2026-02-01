<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\ConfirmPasswordReset\ConfirmPasswordResetCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\PasswordResetConfirmInput;
use App\Presentation\User\State\PasswordResetConfirmProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PasswordResetConfirmProcessorTest extends KernelTestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private PasswordResetConfirmProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->processor = new PasswordResetConfirmProcessor(
            $this->commandBus
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidPasswordResetConfirmInput();

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($input) {
                $this->assertInstanceOf(ConfirmPasswordResetCommand::class, $command);
                $this->assertSame($input->token, $command->token);
                $this->assertSame($input->newPassword, $command->newPassword);

                return true;
            }));

        $this->processor->process($input, $this->operation);
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

    public function testProcessThrowsLogicExceptionForNullInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(null, $this->operation);
    }

    public function testProcessThrowsLogicExceptionForStringInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process('invalid', $this->operation);
    }

    public function testProcessThrowsLogicExceptionForArrayInput(): void
    {
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process(['token' => 'test', 'newPassword' => 'test'], $this->operation);
    }

    private function createValidPasswordResetConfirmInput(): PasswordResetConfirmInput
    {
        $input = new PasswordResetConfirmInput();
        $input->token = 'valid-reset-token-123';
        $input->newPassword = 'NewPassword123!';
        $input->confirmNewPassword = 'NewPassword123!';

        return $input;
    }
}
