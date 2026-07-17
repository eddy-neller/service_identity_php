<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\UseCase\Command\UpdateUserByAdmin\UpdateUserByAdminCommand;
use App\Application\User\UseCase\Command\UpdateUserByAdmin\UpdateUserByAdminCommandHandler;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateUserByAdminTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UserUniquenessCheckerInterface&MockObject $uniquenessChecker;

    private UpdateUserByAdminCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->uniquenessChecker = $this->createMock(UserUniquenessCheckerInterface::class);
        $this->handler = new UpdateUserByAdminCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->clock,
            $this->transactional,
            $this->uniquenessChecker,
        );
    }

    public function testHandleUpdatesAllFieldsWhenProvided(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = $this->createUser($userId);
        $newUsername = 'newusername';
        $newEmail = 'newemail@example.com';
        $newFirstname = 'NewFirstname';
        $newLastname = 'NewLastname';
        $newRoles = ['ROLE_ADMIN'];
        $newStatusInt = UserStatus::BLOCKED;
        $newStatus = UserStatus::fromInt($newStatusInt);
        $newPassword = 'newpassword';
        $hashedPassword = new HashedPassword('hashed-new-password');

        $command = new UpdateUserByAdminCommand(
            userId: $userId->toString(),
            email: $newEmail,
            username: $newUsername,
            plainPassword: $newPassword,
            roles: $newRoles,
            status: $newStatusInt,
            firstname: $newFirstname,
            lastname: $newLastname,
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAvailable')
            ->with(EmailAddress::fromString($newEmail), $userId);

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureUsernameAvailable')
            ->with(Username::fromString($newUsername), $userId);

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

        $output = $this->handler->handle($command);

        $this->assertSame($userId->toString(), $output->id);
        $this->assertSame($newUsername, $output->username);
        $this->assertSame($newEmail, $output->email);
        $this->assertSame($newFirstname, $output->firstname);
        $this->assertSame($newLastname, $output->lastname);
        $this->assertSame($newRoles, $output->roles);
        $this->assertSame($newStatus->toInt(), $output->status);
        $this->assertSame($newUsername, $user->getUsername()->toString());
        $this->assertTrue($user->getEmail()->equals(EmailAddress::fromString($newEmail)));
        $this->assertSame($newFirstname, $user->getFirstname()?->toString());
        $this->assertSame($newLastname, $user->getLastname()?->toString());
        $this->assertSame($newRoles, $user->getRoles()->all());
        $this->assertSame($newStatus->toInt(), $user->getStatus()->toInt());
    }

    public function testHandleUpdatesOnlyProvidedFields(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440001');
        $user = $this->createUser($userId);
        $originalEmail = $user->getEmail();
        $newUsername = 'newusername';

        $command = new UpdateUserByAdminCommand(
            userId: $userId->toString(),
            email: null,
            username: $newUsername,
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->uniquenessChecker->expects($this->never())
            ->method('ensureEmailAvailable');

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureUsernameAvailable')
            ->with(Username::fromString($newUsername), $userId);

        $this->passwordHasher->expects($this->never())
            ->method('hash');

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

        $output = $this->handler->handle($command);

        $this->assertSame($userId->toString(), $output->id);
        $this->assertSame($newUsername, $output->username);
        $this->assertSame($originalEmail->toString(), $output->email);
        $this->assertSame($newUsername, $user->getUsername()->toString());
        $this->assertTrue($user->getEmail()->equals($originalEmail));
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440002');
        $command = new UpdateUserByAdminCommand(
            userId: $userId->toString(),
        );

        $this->uniquenessChecker->expects($this->never())
            ->method('ensureEmailAvailable');

        $this->uniquenessChecker->expects($this->never())
            ->method('ensureUsernameAvailable');

        $this->passwordHasher->expects($this->never())
            ->method('hash');

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

    public function testHandleThrowsExceptionWhenEmailAlreadyUsed(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440003');
        $user = $this->createUser($userId);
        $conflictingEmail = 'taken@example.com';

        $command = new UpdateUserByAdminCommand(
            userId: $userId->toString(),
            email: $conflictingEmail,
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAvailable')
            ->with(EmailAddress::fromString($conflictingEmail), $userId)
            ->willThrowException(new EmailAlreadyUsedException());

        $this->uniquenessChecker->expects($this->never())
            ->method('ensureUsernameAvailable');

        $this->passwordHasher->expects($this->never())
            ->method('hash');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(EmailAlreadyUsedException::class);

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenUsernameAlreadyUsed(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440004');
        $user = $this->createUser($userId);
        $conflictingUsername = 'taken';

        $command = new UpdateUserByAdminCommand(
            userId: $userId->toString(),
            username: $conflictingUsername,
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->uniquenessChecker->expects($this->never())
            ->method('ensureEmailAvailable');

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureUsernameAvailable')
            ->with(Username::fromString($conflictingUsername), $userId)
            ->willThrowException(new UsernameAlreadyUsedException());

        $this->passwordHasher->expects($this->never())
            ->method('hash');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->repository->expects($this->never())
            ->method('save');

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->handler->handle($command);
    }

    private function createUser(UserId $userId): User
    {
        return User::register(
            id: $userId,
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
