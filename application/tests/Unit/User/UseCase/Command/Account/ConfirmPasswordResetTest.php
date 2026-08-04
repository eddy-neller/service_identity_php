<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Account;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Account\ConfirmPasswordReset\ConfirmPasswordResetCommand;
use App\Application\User\UseCase\Command\Account\ConfirmPasswordReset\ConfirmPasswordResetCommandHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\ResetPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ConfirmPasswordResetTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private TokenProviderInterface&MockObject $tokenProvider;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private TransactionalInterface&MockObject $transactional;

    private ClockInterface&MockObject $clock;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private ConfirmPasswordResetCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new ConfirmPasswordResetCommandHandler(
            $this->repository,
            $this->tokenProvider,
            $this->passwordHasher,
            $this->clock,
            $this->transactional,
            $this->eventDispatcher,
        );
    }

    public function testHandleResetsPasswordWhenTokenIsValid(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatchAll');

        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $newPassword = 'new-password';
        $hashedPassword = 'hashed-new-password';
        $command = new ConfirmPasswordResetCommand($token, $newPassword);
        $user = $this->createUserWithResetToken($email, $rawToken, time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($newPassword)
            ->willReturn($hashedPassword);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->handler->handle($command);

        $this->assertSame($hashedPassword, $user->getPassword()->toString());
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $newPassword = 'new-password';
        $command = new ConfirmPasswordResetCommand($token, $newPassword);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->willReturn('hashed-new-password');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn(null);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('Password reset token is invalid.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenTokenExpired(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $newPassword = 'new-password';
        $command = new ConfirmPasswordResetCommand($token, $newPassword);
        $user = $this->createUserWithResetToken($email, $rawToken, time() - 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($newPassword)
            ->willReturn('hashed-new-password');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('Token de réinitialisation expiré.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenTokenMismatch(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $newPassword = 'new-password';
        $command = new ConfirmPasswordResetCommand($token, $newPassword);
        $user = $this->createUserWithResetToken($email, 'different-token', time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('hash')
            ->with($newPassword)
            ->willReturn('hashed-new-password');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('Token de réinitialisation invalide.');

        $this->handler->handle($command);
    }

    private function createUserWithResetToken(string $email, string $token, int $ttl): User
    {
        $user = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString($email),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );

        $resetPassword = ResetPassword::create(
            token: $token,
            tokenTtl: $ttl,
        );

        $reflection = new ReflectionProperty(User::class, 'resetPassword');
        $reflection->setValue($user, $resetPassword);

        return $user;
    }
}
