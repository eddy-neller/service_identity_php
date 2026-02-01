<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\ValidateActivation\ValidateActivationCommand;
use App\Application\User\UseCase\Command\ValidateActivation\ValidateActivationCommandHandler;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User;
use App\Domain\User\Preference\ValueObject\Preferences;
use App\Domain\User\Security\ValueObject\ActiveEmail;
use App\Domain\User\Security\ValueObject\HashedPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ValidateActivationTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private TokenProviderInterface&MockObject $tokenProvider;

    private TransactionalInterface&MockObject $transactional;

    private ClockInterface&MockObject $clock;

    private ValidateActivationCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new ValidateActivationCommandHandler(
            $this->repository,
            $this->tokenProvider,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleActivatesUserWhenTokenIsValid(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $command = new ValidateActivationCommand($token);
        $user = $this->createUserWithActivationToken($email, $rawToken, time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByActivationToken')
            ->with($rawToken)
            ->willReturn($user);

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

        $this->assertTrue($user->isActive());
    }

    public function testHandleThrowsExceptionWhenUserNotFound(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $command = new ValidateActivationCommand($token);

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByActivationToken')
            ->with($rawToken)
            ->willReturn(null);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found for this token.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenEmailMismatch(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $command = new ValidateActivationCommand($token);
        $user = $this->createUserWithActivationToken('other@example.com', $rawToken, time() + 3600);

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByActivationToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('User not found for this token.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenTokenExpired(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $command = new ValidateActivationCommand($token);
        $user = $this->createUserWithActivationToken($email, $rawToken, time() - 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByActivationToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage('Token d\'activation expiré.');

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionWhenTokenMismatch(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $command = new ValidateActivationCommand($token);
        $user = $this->createUserWithActivationToken($email, 'different-token', time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByActivationToken')
            ->with($rawToken)
            ->willReturn($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->expectException(UserDomainException::class);
        $this->expectExceptionMessage("Token d'activation invalide.");

        $this->handler->handle($command);
    }

    private function createUserWithActivationToken(string $email, string $token, int $ttl): User
    {
        $user = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: new Username('testuser'),
            email: new EmailAddress($email),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );

        $activeEmail = new ActiveEmail(
            mailSent: 1,
            token: $token,
            tokenTtl: $ttl,
        );

        $reflection = new ReflectionProperty(User::class, 'activeEmail');
        $reflection->setValue($user, $activeEmail);

        return $user;
    }
}
