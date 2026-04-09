<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\Service;

use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Service\UserUniquenessChecker;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User;
use App\Domain\User\Preference\ValueObject\Preferences;
use App\Domain\User\Security\ValueObject\HashedPassword;
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
        $this->expectExceptionMessage('Adresse email déjà utilisée.');

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
        $this->expectExceptionMessage('Nom d\'utilisateur déjà utilisé.');

        $this->checker->ensureEmailAndUsernameAvailable($email, $username);
    }

    private function createDomainUser(string $email, string $username): User
    {
        return User::register(
            UserId::fromString('00000000-0000-4000-8000-000000000000'),
            Username::fromString($username),
            EmailAddress::fromString($email),
            new HashedPassword('hashed-password'),
            new Preferences(),
            new DateTimeImmutable('2024-01-01T00:00:00Z'),
        );
    }
}
