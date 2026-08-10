<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\User\Account\AccountRecovery;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Account\RequestPasswordReset\RequestPasswordResetCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\Account\AccountRecovery\PasswordResetRequestInput;
use App\Presentation\User\State\Account\AccountRecovery\PasswordResetRequestProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PasswordResetRequestProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private PasswordResetRequestProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->processor = new PasswordResetRequestProcessor(
            $this->commandBus,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidInput();

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($input) {
                $this->assertInstanceOf(RequestPasswordResetCommand::class, $command);
                $this->assertSame($input->email, $command->email);

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

    private function createValidInput(): PasswordResetRequestInput
    {
        $input = new PasswordResetRequestInput();
        $input->email = 'test@example.com';

        return $input;
    }
}
