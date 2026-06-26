<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Query;

use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Query\CheckPasswordResetToken\CheckPasswordResetTokenQuery;
use App\Application\User\UseCase\Query\CheckPasswordResetToken\CheckPasswordResetTokenQueryHandler;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CheckPasswordResetTokenTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private TokenProviderInterface&MockObject $tokenProvider;

    private CheckPasswordResetTokenQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->handler = new CheckPasswordResetTokenQueryHandler(
            $this->repository,
            $this->tokenProvider,
        );
    }

    public function testHandleReturnsValidWhenTokenIsValid(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $query = new CheckPasswordResetTokenQuery($token);
        $user = $this->createUserWithResetToken($email, $rawToken, time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $output = $this->handler->handle($query);

        $this->assertTrue($output);
    }

    public function testHandleReturnsInvalidWhenUserNotFound(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $query = new CheckPasswordResetTokenQuery($token);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn(null);

        $output = $this->handler->handle($query);

        $this->assertFalse($output);
    }

    public function testHandleReturnsInvalidWhenEmailMismatch(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $query = new CheckPasswordResetTokenQuery($token);
        $user = $this->createUserWithResetToken('other@example.com', $rawToken, time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $output = $this->handler->handle($query);

        $this->assertFalse($output);
    }

    public function testHandleReturnsInvalidWhenTokenExpired(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $query = new CheckPasswordResetTokenQuery($token);
        $user = $this->createUserWithResetToken($email, $rawToken, time() - 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $output = $this->handler->handle($query);

        $this->assertFalse($output);
    }

    public function testHandleReturnsInvalidWhenTokenMismatch(): void
    {
        $token = 'encoded-token';
        $email = 'test@example.com';
        $rawToken = 'raw-token';
        $query = new CheckPasswordResetTokenQuery($token);
        $user = $this->createUserWithResetToken($email, 'different-token', time() + 3600);

        $this->tokenProvider->expects($this->once())
            ->method('split')
            ->with($token)
            ->willReturn(['email' => $email, 'token' => $rawToken]);

        $this->repository->expects($this->once())
            ->method('findByResetPasswordToken')
            ->with($rawToken)
            ->willReturn($user);

        $output = $this->handler->handle($query);

        $this->assertFalse($output);
    }

    private function createUserWithResetToken(string $email, string $token, int $ttl): User
    {
        $user = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString($email),
            password: new HashedPassword('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );

        $resetPassword = new ResetPassword(
            token: $token,
            tokenTtl: $ttl,
        );

        $reflection = new ReflectionProperty(User::class, 'resetPassword');
        $reflection->setValue($user, $resetPassword);

        return $user;
    }
}
