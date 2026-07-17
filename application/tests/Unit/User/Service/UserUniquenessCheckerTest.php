<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\Service;

use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Service\UserUniquenessChecker;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserUniquenessCheckerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private UserUniquenessChecker $checker;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->checker = new UserUniquenessChecker($this->repository);
    }

    public function testEnsureEmailAndUsernameAvailableSucceedsWhenBothAvailable(): void
    {
        $email = EmailAddress::fromString('test@example.com');
        $username = Username::fromString('testuser');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn(null);

        $this->checker->ensureEmailAndUsernameAvailable($email, $username);
    }

    public function testEnsureEmailAndUsernameAvailableThrowsWhenEmailAlreadyUsed(): void
    {
        $email = EmailAddress::fromString('existing@example.com');
        $username = Username::fromString('testuser');
        $existingUser = $this->createDomainUser(email: 'existing@example.com', username: 'existinguser');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($existingUser);

        $this->repository->expects($this->never())
            ->method('findByUsername');

        $this->expectException(EmailAlreadyUsedException::class);
        $this->expectExceptionMessage('Email address is already used.');

        $this->checker->ensureEmailAndUsernameAvailable($email, $username);
    }

    public function testEnsureEmailAndUsernameAvailableThrowsWhenUsernameAlreadyUsed(): void
    {
        $email = EmailAddress::fromString('test@example.com');
        $username = Username::fromString('existinguser');
        $existingUser = $this->createDomainUser(email: 'test@example.com', username: 'existinguser');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn($existingUser);

        $this->expectException(UsernameAlreadyUsedException::class);
        $this->expectExceptionMessage('Username is already used.');

        $this->checker->ensureEmailAndUsernameAvailable($email, $username);
    }

    public function testEnsureEmailAvailableSucceedsWhenEmailFree(): void
    {
        $email = EmailAddress::fromString('free@example.com');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->checker->ensureEmailAvailable($email);
    }

    public function testEnsureEmailAvailableSucceedsWhenEmailBelongsToExcludedUser(): void
    {
        $email = EmailAddress::fromString('owner@example.com');
        $ownerId = UserId::fromString('11111111-1111-4111-8111-111111111111');
        $owner = $this->createDomainUser(email: 'owner@example.com', username: 'owner', id: $ownerId);

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($owner);

        $this->checker->ensureEmailAvailable($email, $ownerId);
    }

    public function testEnsureEmailAvailableThrowsWhenEmailBelongsToAnotherUser(): void
    {
        $email = EmailAddress::fromString('taken@example.com');
        $otherId = UserId::fromString('22222222-2222-4222-8222-222222222222');
        $other = $this->createDomainUser(email: 'taken@example.com', username: 'other', id: $otherId);
        $currentId = UserId::fromString('33333333-3333-4333-8333-333333333333');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($other);

        $this->expectException(EmailAlreadyUsedException::class);

        $this->checker->ensureEmailAvailable($email, $currentId);
    }

    public function testEnsureEmailAvailableThrowsWhenEmailUsedAndNoExclusion(): void
    {
        $email = EmailAddress::fromString('taken@example.com');
        $existing = $this->createDomainUser(email: 'taken@example.com', username: 'existing');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($existing);

        $this->expectException(EmailAlreadyUsedException::class);

        $this->checker->ensureEmailAvailable($email);
    }

    public function testEnsureUsernameAvailableSucceedsWhenUsernameFree(): void
    {
        $username = Username::fromString('freeuser');

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn(null);

        $this->checker->ensureUsernameAvailable($username);
    }

    public function testEnsureUsernameAvailableSucceedsWhenUsernameBelongsToExcludedUser(): void
    {
        $username = Username::fromString('owner');
        $ownerId = UserId::fromString('11111111-1111-4111-8111-111111111111');
        $owner = $this->createDomainUser(email: 'owner@example.com', username: 'owner', id: $ownerId);

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn($owner);

        $this->checker->ensureUsernameAvailable($username, $ownerId);
    }

    public function testEnsureUsernameAvailableThrowsWhenUsernameBelongsToAnotherUser(): void
    {
        $username = Username::fromString('taken');
        $otherId = UserId::fromString('22222222-2222-4222-8222-222222222222');
        $other = $this->createDomainUser(email: 'taken@example.com', username: 'taken', id: $otherId);
        $currentId = UserId::fromString('33333333-3333-4333-8333-333333333333');

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn($other);

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->checker->ensureUsernameAvailable($username, $currentId);
    }

    public function testEnsureUsernameAvailableThrowsWhenUsernameUsedAndNoExclusion(): void
    {
        $username = Username::fromString('taken');
        $existing = $this->createDomainUser(email: 'taken@example.com', username: 'taken');

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with($username)
            ->willReturn($existing);

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->checker->ensureUsernameAvailable($username);
    }

    private function createDomainUser(string $email, string $username, ?UserId $id = null): User
    {
        return User::register(
            $id ?? UserId::fromString('00000000-0000-4000-8000-000000000000'),
            Username::fromString($username),
            EmailAddress::fromString($email),
            HashedPassword::fromString('hashed-password'),
            new Preferences(),
            new DateTimeImmutable('2024-01-01T00:00:00Z'),
        );
    }
}
