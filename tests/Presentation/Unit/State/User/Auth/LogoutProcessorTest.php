<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\User\Auth;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Auth\Logout\LogoutCommand;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use App\Presentation\User\Dto\Auth\LogoutInput;
use App\Presentation\User\State\Auth\LogoutProcessor;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;

final class LogoutProcessorTest extends TestCase
{
    public function testProcessDispatchesLogoutForTheAuthenticatedUser(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $security = $this->createMock(Security::class);
        $operation = $this->createMock(Operation::class);
        $operation->expects($this->never())->method('getName');
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('getId')
            ->willReturn(Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'));
        $security->expects($this->once())->method('getUser')->willReturn($user);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (mixed $command): bool {
                $this->assertInstanceOf(LogoutCommand::class, $command);
                $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $command->userId);

                return true;
            }));

        $input = new LogoutInput();
        $input->refreshToken = 'refresh';
        (new LogoutProcessor($bus, $security))->process($input, $operation);
    }
}
