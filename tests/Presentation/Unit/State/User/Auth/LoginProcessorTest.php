<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\User\Auth;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\ReadModel\AuthTokens;
use App\Application\User\UseCase\Command\Auth\Login\LoginCommand;
use App\Presentation\User\Dto\Auth\LoginInput;
use App\Presentation\User\Presenter\AuthResourcePresenter;
use App\Presentation\User\State\Auth\LoginProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private Operation&MockObject $operation;

    private LoginProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())->method('getName');
        $this->processor = new LoginProcessor($this->commandBus, new AuthResourcePresenter());
    }

    public function testProcessDispatchesLoginAndPresentsTokens(): void
    {
        $input = new LoginInput();
        $input->email = 'john@example.com';
        $input->password = 'ChangeMe123!';

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (mixed $command): AuthTokens {
                $this->assertInstanceOf(LoginCommand::class, $command);
                $this->assertSame('john@example.com', $command->email);

                return new AuthTokens('access', 'refresh', 'Bearer', 900);
            });

        $result = $this->processor->process($input, $this->operation);

        $this->assertSame('access', $result->accessToken);
        $this->assertSame('refresh', $result->refreshToken);
    }
}
