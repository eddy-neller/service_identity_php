<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\User\UseCase\Command\Onboarding;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\UseCase\Command\Onboarding\RegisterUser\RegisterUserCommand;
use App\Application\User\UseCase\Command\Onboarding\RegisterUser\RegisterUserCommandHandler;
use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Exception\Uniqueness\EmailAlreadyUsedException;
use App\Domain\User\Exception\Uniqueness\UsernameAlreadyUsedException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegisterUserTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;

    private PasswordHasherInterface&MockObject $passwordHasher;

    private TokenProviderInterface&MockObject $tokenProvider;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private ConfigInterface&MockObject $config;

    private UserUniquenessCheckerInterface&MockObject $uniquenessChecker;

    private DomainEventBusInterface&MockObject $eventBus;

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
        $this->eventBus = $this->createMock(DomainEventBusInterface::class);
        $this->handler = new RegisterUserCommandHandler(
            $this->repository,
            $this->passwordHasher,
            $this->tokenProvider,
            $this->clock,
            $this->transactional,
            $this->config,
            $this->uniquenessChecker,
            $this->eventBus,
        );
    }

    public function testHandleCreatesAndSavesUser(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'test@example.com';
        $username = 'testuser';
        $plainPassword = 'password123';
        $hashedPassword = 'hashed-password';
        $token = 'activation-token';
        $transactionCompleted = false;

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
            ->method('add')
            ->with($this->callback(function (User $user) use ($userId, $username, $email, $hashedPassword) {
                return $user->getId()->equals($userId)
                    && $user->getUsername()->toString() === $username
                    && $user->getEmail()->equals(EmailAddress::fromString($email))
                    && $user->getPassword()->toString() === $hashedPassword;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) use (&$transactionCompleted) {
                $result = $callback();
                $transactionCompleted = true;

                return $result;
            });

        $this->eventBus->expects($this->once())
            ->method('publishAll')
            ->willReturnCallback(function (array $events) use (&$transactionCompleted): void {
                $this->assertFalse($transactionCompleted, 'La publication doit avoir lieu dans la transaction.');
                $this->assertCount(2, $events);
                $this->assertInstanceOf(UserRegisteredEvent::class, $events[0]);
                $this->assertInstanceOf(ActivationEmailRequestedEvent::class, $events[1]);
            });

        $output = $this->handler->handle($command);

        $this->assertSame($userId->toString(), $output->id);
        $this->assertSame($email, $output->email);
        $this->assertSame($username, $output->username);
    }

    public function testHandleThrowsWhenEmailAlreadyUsed(): void
    {
        $email = 'test@example.com';
        $username = 'testuser';

        $command = new RegisterUserCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
        );

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable')
            ->with(EmailAddress::fromString($email), Username::fromString($username))
            ->willThrowException(new EmailAlreadyUsedException());

        // Le contrôle passe avant le hash : un conflit ne doit coûter aucun bcrypt,
        // ni ouvrir de transaction.
        $this->passwordHasher->expects($this->never())->method('hash');
        $this->repository->expects($this->never())->method('nextIdentity');
        $this->repository->expects($this->never())->method('add');
        $this->clock->expects($this->never())->method('now');
        $this->tokenProvider->expects($this->never())->method('generateRandomToken');
        $this->config->expects($this->never())->method('getString');
        $this->transactional->expects($this->never())->method('transactional');
        $this->eventBus->expects($this->never())->method('publishAll');

        $this->expectException(EmailAlreadyUsedException::class);

        $this->handler->handle($command);
    }

    public function testHandleThrowsWhenUsernameAlreadyUsed(): void
    {
        $email = 'test2@example.com';
        $username = 'existinguser';

        $command = new RegisterUserCommand(
            email: $email,
            username: $username,
            plainPassword: 'password123',
        );

        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable')
            ->with(EmailAddress::fromString($email), Username::fromString($username))
            ->willThrowException(new UsernameAlreadyUsedException());

        $this->passwordHasher->expects($this->never())->method('hash');
        $this->repository->expects($this->never())->method('nextIdentity');
        $this->repository->expects($this->never())->method('add');
        $this->clock->expects($this->never())->method('now');
        $this->tokenProvider->expects($this->never())->method('generateRandomToken');
        $this->config->expects($this->never())->method('getString');
        $this->transactional->expects($this->never())->method('transactional');
        $this->eventBus->expects($this->never())->method('publishAll');

        $this->expectException(UsernameAlreadyUsedException::class);

        $this->handler->handle($command);
    }

    public function testHandlePropagatesEventPublicationFailureFromInsideTransaction(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $transactionCompleted = false;
        $command = new RegisterUserCommand(
            email: 'test@example.com',
            username: 'testuser',
            plainPassword: 'password123',
        );

        $this->repository->expects($this->once())->method('nextIdentity')->willReturn($userId);
        $this->repository->expects($this->once())->method('add');
        $this->passwordHasher->expects($this->once())->method('hash')->willReturn('hashed-password');
        $this->tokenProvider->expects($this->once())
            ->method('generateRandomToken')
            ->willReturn('activation-token');
        $this->clock->expects($this->once())->method('now')->willReturn($now);
        $this->config->expects($this->once())
            ->method('getString')
            ->with('register_token_ttl', 'P2D')
            ->willReturn('P2D');
        $this->uniquenessChecker->expects($this->once())
            ->method('ensureEmailAndUsernameAvailable');
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) use (&$transactionCompleted): mixed {
                $result = $callback();
                $transactionCompleted = true;

                return $result;
            });
        $this->eventBus->expects($this->once())
            ->method('publishAll')
            ->willReturnCallback(function () use (&$transactionCompleted): never {
                $this->assertFalse($transactionCompleted, 'La publication doit avoir lieu dans la transaction.');

                throw new RuntimeException('Event dispatch failed.');
            });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Event dispatch failed.');

        $this->handler->handle($command);
    }
}
