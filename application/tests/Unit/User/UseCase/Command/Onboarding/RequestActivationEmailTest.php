<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Onboarding;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Onboarding\RequestActivationEmail\RequestActivationEmailCommand;
use App\Application\User\UseCase\Command\Onboarding\RequestActivationEmail\RequestActivationEmailCommandHandler;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestActivationEmailTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private TokenProviderInterface&MockObject $tokenProvider;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private ConfigInterface&MockObject $config;

    private RequestActivationEmailCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->handler = new RequestActivationEmailCommandHandler(
            $this->repository,
            $this->tokenProvider,
            $this->clock,
            $this->transactional,
            $this->config,
        );
    }

    public function testHandleSendsActivationEmailWhenUserExists(): void
    {
        $email = 'test@example.com';
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $token = 'activation-token';
        $user = $this->createUser($email);
        $command = new RequestActivationEmailCommand($email);

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with(EmailAddress::fromString($email))
            ->willReturn($user);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->tokenProvider->expects($this->once())
            ->method('generateRandomToken')
            ->willReturn($token);

        $this->config->expects($this->once())
            ->method('getString')
            ->with('register_token_ttl', 'P2D')
            ->willReturn('P2D');

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

    public function testHandleDoesNothingWhenUserNotFound(): void
    {
        $email = 'nonexistent@example.com';
        $command = new RequestActivationEmailCommand($email);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable('2024-01-01 12:00:00'));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->config->expects($this->once())
            ->method('getString')
            ->with('register_token_ttl', 'P2D')
            ->willReturn('P2D');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with(EmailAddress::fromString($email))
            ->willReturn(null);

        $this->tokenProvider->expects($this->once())
            ->method('generateRandomToken')
            ->willReturn('activation-token');

        $this->handler->handle($command);
    }

    private function createUser(string $email): User
    {
        return User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString($email),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
