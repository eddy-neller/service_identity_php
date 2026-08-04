<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Auth;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\AccessTokenProviderInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\RefreshTokenHasherInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Service\AuthTokenIssuer;
use App\Application\User\UseCase\Command\Auth\Login\LoginCommand;
use App\Application\User\UseCase\Command\Auth\Login\LoginCommandHandler;
use App\Domain\User\Event\Security\UserReauthenticationRequiredEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptRegisteredEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptsResetEvent;
use App\Domain\User\Exception\Security\InvalidCredentialsException;
use App\Domain\User\Exception\Security\UserLockedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\RefreshTokenId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private ClockInterface&MockObject $clock;

    private ConfigInterface&MockObject $config;

    private TransactionalInterface&MockObject $transactional;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private AccessTokenProviderInterface&MockObject $accessTokenProvider;

    private TokenProviderInterface&MockObject $tokenProvider;

    private RefreshTokenHasherInterface&MockObject $refreshTokenHasher;

    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;

    private LoginCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->accessTokenProvider = $this->createMock(AccessTokenProviderInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->refreshTokenHasher = $this->createMock(RefreshTokenHasherInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);

        $tokenIssuer = new AuthTokenIssuer(
            $this->accessTokenProvider,
            $this->tokenProvider,
            $this->refreshTokenHasher,
            $this->refreshTokenRepository,
            $this->config,
        );

        $this->handler = new LoginCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $tokenIssuer,
            $this->clock,
            $this->config,
            $this->transactional,
            $this->eventDispatcher,
        );
    }

    public function testHandlePublishesWrongPasswordEventAfterTransactionBeforeInvalidCredentials(): void
    {
        $now = new DateTimeImmutable('2026-07-18 11:00:00');
        $user = $this->createActiveUser();
        $transactionCompleted = false;

        $this->accessTokenProvider->expects($this->never())->method('issue');
        $this->tokenProvider->expects($this->never())->method('generateRandomToken');
        $this->refreshTokenHasher->expects($this->never())->method('hash');
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->configureLoginAttempt($user, $now, maxAttempts: 3);
        $this->passwordHasher->expects($this->once())
            ->method('verify')
            ->with('hash', 'invalid-password')
            ->willReturn(false);
        $this->repository->expects($this->once())->method('save')->with($user);
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) use (&$transactionCompleted): mixed {
                $result = $callback();
                $transactionCompleted = true;

                return $result;
            });
        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$transactionCompleted): void {
                $this->assertTrue($transactionCompleted);
                $this->assertCount(1, $events);
                $this->assertInstanceOf(UserWrongPasswordAttemptRegisteredEvent::class, $events[0]);
            });

        $this->expectException(InvalidCredentialsException::class);

        $this->handler->handle(new LoginCommand('john@example.com', 'invalid-password'));
    }

    public function testHandlePublishesLockEventsAfterTransactionBeforeLockedException(): void
    {
        $now = new DateTimeImmutable('2026-07-18 11:00:00');
        $user = $this->createActiveUser();
        $transactionCompleted = false;

        $this->accessTokenProvider->expects($this->never())->method('issue');
        $this->tokenProvider->expects($this->never())->method('generateRandomToken');
        $this->refreshTokenHasher->expects($this->never())->method('hash');
        $this->refreshTokenRepository->expects($this->never())->method('save');

        $this->configureLoginAttempt($user, $now, maxAttempts: 1);
        $this->passwordHasher->expects($this->once())->method('verify')->willReturn(false);
        $this->repository->expects($this->once())->method('save')->with($user);
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) use (&$transactionCompleted): mixed {
                $result = $callback();
                $transactionCompleted = true;

                return $result;
            });
        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$transactionCompleted): void {
                $this->assertTrue($transactionCompleted);
                $this->assertCount(2, $events);
                $this->assertInstanceOf(UserWrongPasswordAttemptRegisteredEvent::class, $events[0]);
                $this->assertInstanceOf(UserReauthenticationRequiredEvent::class, $events[1]);
            });

        $this->expectException(UserLockedException::class);

        $this->handler->handle(new LoginCommand('john@example.com', 'invalid-password'));
    }

    public function testHandlePublishesResetEventAfterTransactionBeforeReturningTokens(): void
    {
        $now = new DateTimeImmutable('2026-07-18 11:00:00');
        $user = $this->createActiveUser();
        $user->registerWrongPasswordAttempt(3, $now->modify('-1 minute'));
        $user->clearDomainEvents();

        $transactionCompleted = false;

        $this->configureLoginAttempt($user, $now, maxAttempts: 3);
        $this->passwordHasher->expects($this->once())->method('verify')->willReturn(true);
        $this->repository->expects($this->once())->method('save')->with($user);
        $this->accessTokenProvider->expects($this->once())
            ->method('issue')
            ->with($user)
            ->willReturn(['token' => 'access-token', 'expiresIn' => 3600]);
        $this->tokenProvider->expects($this->once())
            ->method('generateRandomToken')
            ->willReturn('refresh-token');
        $this->refreshTokenHasher->expects($this->once())
            ->method('hash')
            ->with('refresh-token')
            ->willReturn('refresh-token-hash');
        $this->refreshTokenRepository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn(RefreshTokenId::fromString('550e8400-e29b-41d4-a716-446655440001'));
        $this->refreshTokenRepository->expects($this->once())->method('save');
        $this->config->expects($this->once())
            ->method('getString')
            ->with('jwt_refresh_ttl', 'P30D')
            ->willReturn('P30D');
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) use (&$transactionCompleted): mixed {
                $result = $callback();
                $transactionCompleted = true;

                return $result;
            });
        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$transactionCompleted): void {
                $this->assertTrue($transactionCompleted);
                $this->assertCount(1, $events);
                $this->assertInstanceOf(UserWrongPasswordAttemptsResetEvent::class, $events[0]);
            });

        $tokens = $this->handler->handle(new LoginCommand('john@example.com', 'valid-password'));

        $this->assertSame('access-token', $tokens->accessToken);
        $this->assertSame('refresh-token', $tokens->refreshToken);
        $this->assertSame(1, $user->getLoginCount());
    }

    private function configureLoginAttempt(User $user, DateTimeImmutable $now, int $maxAttempts): void
    {
        $this->clock->expects($this->once())->method('now')->willReturn($now);
        $this->config->expects($this->once())
            ->method('get')
            ->with('app.security.max_login_attempts')
            ->willReturn($maxAttempts);
        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with(EmailAddress::fromString('john@example.com'))
            ->willReturn($user);
    }

    private function createActiveUser(): User
    {
        $user = User::createByAdmin(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            roles: RoleSet::fromArray(['ROLE_USER']),
            status: UserStatus::active(),
            now: new DateTimeImmutable('2026-07-18 10:00:00'),
            preferences: Preferences::create(),
        );
        $user->clearDomainEvents();

        return $user;
    }
}
