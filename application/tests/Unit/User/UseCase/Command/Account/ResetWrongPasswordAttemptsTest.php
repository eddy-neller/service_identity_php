<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Account;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\Account\ResetWrongPasswordAttempts\ResetWrongPasswordAttemptsCommand;
use App\Application\User\UseCase\Command\Account\ResetWrongPasswordAttempts\ResetWrongPasswordAttemptsCommandHandler;
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

final class ResetWrongPasswordAttemptsTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private ResetWrongPasswordAttemptsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new ResetWrongPasswordAttemptsCommandHandler(
            repository: $this->repository,
            clock: $this->clock,
            transactional: $this->transactional,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    public function testHandleResetsAttemptsWhenUserFound(): void
    {
        $this->eventDispatcher->expects($this->once())->method('dispatchAll');

        $user = $this->createUser();
        $this->setResetPassword($user, ResetPassword::create(mailSent: 0, token: 't', tokenTtl: time() + 3600));
        $command = new ResetWrongPasswordAttemptsCommand((string) $user->getId());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($user->getId())
            ->willReturn($user);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(new DateTimeImmutable());

        $this->repository->expects($this->once())
            ->method('save')
            ->with($user);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);

        $this->assertSame(0, $user->getSecurity()->getTotalWrongPassword());
    }

    public function testHandleDoesNothingWhenUserNotFound(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $command = new ResetWrongPasswordAttemptsCommand('550e8400-e29b-41d4-a716-446655440000');

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->clock->expects($this->never())->method('now');
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

    private function setResetPassword(User $user, ResetPassword $resetPassword): void
    {
        $reflection = new ReflectionProperty(User::class, 'resetPassword');
        $reflection->setValue($user, $resetPassword);
    }
}
