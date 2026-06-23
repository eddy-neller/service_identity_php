<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\UseCase\Command\RegisterUser\RegisterUserCommand;
use App\Application\User\UseCase\Command\RegisterUser\RegisterUserCommandHandler;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterUserTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private TokenProviderInterface&MockObject $tokenProvider;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private ConfigInterface&MockObject $config;

    private UserUniquenessCheckerInterface&MockObject $uniquenessChecker;

    private RegisterUserCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->uniquenessChecker = $this->createMock(UserUniquenessCheckerInterface::class);
        $this->handler = new RegisterUserCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->tokenProvider,
            $this->clock,
            $this->transactional,
            $this->config,
            $this->uniquenessChecker,
        );
    }

    public function testHandleCreatesAndSavesUser(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'test@example.com';
        $username = 'testuser';
        $plainPassword = 'password123';
        $hashedPassword = new HashedPassword('hashed-password');
        $token = 'activation-token';

        $command = new RegisterUserCommand(
            email: $email,
            username: $username,
            plainPassword: $plainPassword,
            preferences: ['lang' => 'fr'],
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

        $this->tokenProvider->expects($this->once())
            ->method('generateRandomToken')
            ->willReturn($token);

        $this->config->expects($this->once())
            ->method('getString')
            ->with('register_token_ttl', 'P2D')
            ->willReturn('P2D');

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) use ($userId, $username, $email) {
                return $user->getId()->equals($userId)
                    && $user->getUsername()->toString() === $username
                    && $user->getEmail()->equals(EmailAddress::fromString($email));
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
        $email = 'test@example.com';
        $username = 'testuser';
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $command = new RegisterUserCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
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

        $this->tokenProvider->expects($this->never())
            ->method('generateRandomToken');

        $this->config->expects($this->never())
            ->method('getString');

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
        $email = 'test2@example.com';
        $username = 'existinguser';
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $command = new RegisterUserCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
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

        $this->tokenProvider->expects($this->never())
            ->method('generateRandomToken');

        $this->config->expects($this->never())
            ->method('getString');

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }
}
