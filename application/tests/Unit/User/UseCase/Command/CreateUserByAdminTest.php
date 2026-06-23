<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\UseCase\Command\CreateUserByAdmin\CreateUserByAdminCommand;
use App\Application\User\UseCase\Command\CreateUserByAdmin\CreateUserByAdminCommandHandler;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateUserByAdminTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UserUniquenessCheckerInterface&MockObject $uniquenessChecker;

    private CreateUserByAdminCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->uniquenessChecker = $this->createMock(UserUniquenessCheckerInterface::class);
        $this->handler = new CreateUserByAdminCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->clock,
            $this->transactional,
            $this->uniquenessChecker,
        );
    }

    public function testHandleCreatesUserWithAllFields(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'admin@example.com';
        $username = 'adminuser';
        $firstname = 'Admin';
        $lastname = 'User';
        $plainPassword = 'password123';
        $hashedPassword = new HashedPassword('hashed-password');
        $roles = ['ROLE_ADMIN', 'ROLE_USER'];
        $statusInt = UserStatus::ACTIVE;
        $status = UserStatus::fromInt($statusInt);

        $command = new CreateUserByAdminCommand(
            email: $email,
            username: $username,
            plainPassword: $plainPassword,
            roles: $roles,
            status: $statusInt,
            firstname: $firstname,
            lastname: $lastname,
        );

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($userId);

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable')
            ->with(EmailAddress::fromString($email), Username::fromString($username));

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($plainPassword)
            ->willReturn($hashedPassword);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) use ($userId, $username, $email, $firstname, $lastname, $status, $roles) {
                return $user->getId()->equals($userId)
                    && $user->getUsername()->toString() === $username
                    && $user->getEmail()->equals(EmailAddress::fromString($email))
                    && $user->getFirstname()?->toString() === $firstname
                    && $user->getLastname()?->toString() === $lastname
                    && $user->getStatus()->toInt() === $status->toInt()
                    && $user->getRoles()->all() === array_values(array_unique($roles));
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertInstanceOf(User::class, $output->user);
        $this->assertTrue($output->user->getEmail()->equals(EmailAddress::fromString($email)));
        $this->assertSame($username, $output->user->getUsername()->toString());
    }

    public function testHandleThrowsWhenEmailAlreadyUsed(): void
    {
        $email = 'admin@example.com';
        $username = 'adminuser';
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $command = new CreateUserByAdminCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
            roles: ['ROLE_ADMIN'],
            status: UserStatus::ACTIVE,
        );

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($userId);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable')
            ->with(EmailAddress::fromString($email), Username::fromString($username))
            ->willThrowException(new EmailAlreadyUsedException());

        $this->passwordHasher->expects($this->never())
            ->method('hash');

        $this->expectException(EmailAlreadyUsedException::class);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandleThrowsWhenUsernameAlreadyUsed(): void
    {
        $email = 'new@example.com';
        $username = 'existing-admin';
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $command = new CreateUserByAdminCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
            roles: ['ROLE_ADMIN'],
            status: UserStatus::ACTIVE,
        );

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($userId);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable')
            ->with(EmailAddress::fromString($email), Username::fromString($username))
            ->willThrowException(new UsernameAlreadyUsedException());

        $this->passwordHasher->expects($this->never())
            ->method('hash');

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }
}
