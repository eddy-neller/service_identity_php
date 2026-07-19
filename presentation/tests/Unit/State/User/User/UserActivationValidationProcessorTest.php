<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\User;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\ValidateActivation\ValidateActivationCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\User\UserActivationValidationInput;
use App\Presentation\User\State\User\UserActivationValidationProcessor;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserActivationValidationProcessorTest extends KernelTestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private UserActivationValidationProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $this->processor = new UserActivationValidationProcessor(
            $this->commandBus
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = $this->createValidUserActivationValidationInput();

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($input) {
                $this->assertInstanceOf(ValidateActivationCommand::class, $command);
                $this->assertSame($input->token, $command->token);

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

        $this->processor->process(['token' => 'test'], $this->operation);
    }

    private function createValidUserActivationValidationInput(): UserActivationValidationInput
    {
        $input = new UserActivationValidationInput();
        $input->token = 'valid-activation-token-123';

        return $input;
    }
}
