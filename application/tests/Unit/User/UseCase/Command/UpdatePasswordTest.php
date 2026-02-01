<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\UpdatePassword\UpdatePasswordCommand;
use App\Application\User\UseCase\Command\UpdatePassword\UpdatePasswordCommandHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User;
use App\Domain\User\Preference\ValueObject\Preferences;
use App\Domain\User\Security\ValueObject\HashedPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdatePasswordTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UpdatePasswordCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new UpdatePasswordCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleUpdatesPasswordWhenUserExists(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);
        $newPassword = 'new-password';
        $hashedPassword = new HashedPassword('hashed-new-password');
        $command = new UpdatePasswordCommand($userId, $newPassword);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

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
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $newPassword = 'new-password';
        $command = new UpdatePasswordCommand($userId, $newPassword);

        $this->passwordHasher->expects($this->never())
            ->method('hash');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found.');

        $this->handler->handle($command);
    }

    private function createUser(UserId $userId): User
    {
        return User::register(
            id: $userId,
            username: new Username('testuser'),
            email: new EmailAddress('test@example.com'),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
