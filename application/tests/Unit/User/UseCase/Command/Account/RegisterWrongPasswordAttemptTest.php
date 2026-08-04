<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Account;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Account\RegisterWrongPasswordAttempt\RegisterWrongPasswordAttemptCommand;
use App\Application\User\UseCase\Command\Account\RegisterWrongPasswordAttempt\RegisterWrongPasswordAttemptCommandHandler;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RegisterWrongPasswordAttemptTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private ConfigInterface&MockObject $config;

    private TransactionalInterface&MockObject $transactional;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private RegisterWrongPasswordAttemptCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->handler = new RegisterWrongPasswordAttemptCommandHandler(
            repository: $this->repository,
            clock: $this->clock,
            config: $this->config,
            transactional: $this->transactional,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    public function testHandleIncrementsAttemptsAndBlocksOnThreshold(): void
    {
        $this->eventDispatcher->expects($this->exactly(2))->method('dispatchAll');

        $command = new RegisterWrongPasswordAttemptCommand(email: 'john@example.com');
        $user = $this->createUser();

        $this->config->expects($this->exactly(2))
            ->method('get')
            ->with('app.security.max_login_attempts')
            ->willReturn(2);

        $this->clock->expects($this->exactly(2))
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->repository->expects($this->exactly(2))
            ->method('findByEmail')
            ->with(EmailAddress::fromString('john@example.com'))
            ->willReturn($user);

        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->with($user);

        $this->transactional->expects($this->exactly(2))
            ->method('transactional')
            ->willReturnCallback(static function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
        $this->assertSame(1, $user->getSecurity()->getTotalWrongPassword());

        $this->handler->handle($command);
        $this->assertTrue($user->isLocked());
    }

    public function testHandleDoesNothingWhenUserNotFound(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $command = new RegisterWrongPasswordAttemptCommand(email: 'unknown@example.com');

        $this->config->expects($this->once())
            ->method('get')
            ->with('app.security.max_login_attempts')
            ->willReturn(2);
        $this->clock->expects($this->never())->method('now');

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with(EmailAddress::fromString('unknown@example.com'))
            ->willReturn(null);

        $this->repository->expects($this->never())->method('save');
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->handler->handle($command);
    }

    private function createUser(): User
    {
        return User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }
}
