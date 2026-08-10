<?php

declare(strict_types=1);

namespace App\Tests\Application\Unit\User\UseCase\Command\UserManagement;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\UserManagement\DeleteUserByAdmin\DeleteUserByAdminCommand;
use App\Application\User\UseCase\Command\UserManagement\DeleteUserByAdmin\DeleteUserByAdminCommandHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteUserByAdminTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private DomainEventBusInterface&MockObject $eventBus;

    private DeleteUserByAdminCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventBus = $this->createMock(DomainEventBusInterface::class);
        $this->handler = new DeleteUserByAdminCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
            $this->eventBus,
        );
    }

    public function testHandleDeletesUserWhenFound(): void
    {
        $this->eventBus->expects($this->once())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);

        $command = new DeleteUserByAdminCommand(
            userId: $userId->toString(),
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $this->eventBus->expects($this->never())->method('publishAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $command = new DeleteUserByAdminCommand(
            userId: $userId->toString(),
        );

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('delete');

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($command);
    }

    private function createUser(UserId $userId): User
    {
        return User::register(
            id: $userId,
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
