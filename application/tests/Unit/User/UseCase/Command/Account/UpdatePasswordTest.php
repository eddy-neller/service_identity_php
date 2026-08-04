<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Account;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Account\UpdatePassword\UpdatePasswordCommand;
use App\Application\User\UseCase\Command\Account\UpdatePassword\UpdatePasswordCommandHandler;
use App\Domain\User\Exception\Security\InvalidCurrentPasswordException;
use App\Domain\User\Exception\Security\SamePasswordException;
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

final class UpdatePasswordTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private UpdatePasswordCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new UpdatePasswordCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->clock,
            $this->transactional,
            $this->eventDispatcher,
        );
    }

    public function testHandleUpdatesPasswordWhenUserExists(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatchAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);
        $currentPassword = 'current-password';
        $newPassword = 'new-password';
        $hashedPassword = 'hashed-new-password';
        $command = new UpdatePasswordCommand($userId->toString(), $currentPassword, $newPassword);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->passwordHasher->expects($this->exactly(2))
            ->method('verify')
            ->willReturnMap([
                [$user->getPassword()->toString(), $currentPassword, true],
                [$user->getPassword()->toString(), $newPassword, false],
            ]);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($newPassword)
            ->willReturn($hashedPassword);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->repository->expects($this->once())
            ->method('save')
            ->with($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);

        $this->assertSame($hashedPassword, $user->getPassword()->toString());
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $command = new UpdatePasswordCommand($userId->toString(), 'current-password', 'new-password');

        $this->passwordHasher->expects($this->never())
            ->method('verify');

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with('new-password')
            ->willReturn('hashed-new-password');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenCurrentPasswordIsInvalid(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440002');
        $user = $this->createUser($userId);
        $command = new UpdatePasswordCommand($userId->toString(), 'wrong-password', 'new-password');

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('verify')
            ->with($user->getPassword()->toString(), 'wrong-password')
            ->willReturn(false);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with('new-password')
            ->willReturn('hashed-new-password');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(InvalidCurrentPasswordException::class);

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenNewPasswordMatchesCurrentPassword(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440003');
        $user = $this->createUser($userId);
        $password = 'current-password';
        $command = new UpdatePasswordCommand($userId->toString(), $password, $password);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->passwordHasher->expects($this->exactly(2))
            ->method('verify')
            ->willReturnMap([
                [$user->getPassword()->toString(), $password, true],
                [$user->getPassword()->toString(), $password, true],
            ]);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($password)
            ->willReturn('hashed-new-password');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(SamePasswordException::class);
        $this->expectExceptionMessage('The new password must be different from the current password.');

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
