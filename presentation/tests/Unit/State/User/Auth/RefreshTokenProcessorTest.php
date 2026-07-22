<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\User\Auth;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\ReadModel\AuthTokens;
use App\Application\User\UseCase\Command\Auth\RefreshToken\RefreshTokenCommand;
use App\Presentation\User\Dto\Auth\RefreshTokenInput;
use App\Presentation\User\Presenter\AuthResourcePresenter;
use App\Presentation\User\State\Auth\RefreshTokenProcessor;
use PHPUnit\Framework\TestCase;

final class RefreshTokenProcessorTest extends TestCase
{
    public function testProcessDispatchesRefreshAndPresentsTokens(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $operation = $this->createMock(Operation::class);
        $operation->expects($this->never())->method('getName');
        $processor = new RefreshTokenProcessor($bus, new AuthResourcePresenter());
        $input = new RefreshTokenInput();
        $input->refreshToken = 'refresh';

        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RefreshTokenCommand::class))
            ->willReturn(new AuthTokens('access', 'new-refresh', 'Bearer', 900));

        $result = $processor->process($input, $operation);

        $this->assertSame('new-refresh', $result->refreshToken);
    }
}
