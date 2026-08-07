<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Messenger\Event\Handler\User;

use App\Application\Shared\Port\ClockInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Event\Security\PasswordResetRequestedEvent;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Messenger\Event\Handler\User\SendResetPasswordEmailHandler;
use App\Infrastructure\Notification\User\UserNotifierInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SendResetPasswordEmailHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string EMAIL = 'john@example.com';

    private DateTimeImmutable $now;

    private UserRepositoryInterface&MockObject $repository;

    private TokenProviderInterface&MockObject $tokenProvider;

    private UserNotifierInterface&MockObject $notifier;

    private DomainEventLedgerInterface&MockObject $ledger;

    private LoggerInterface&MockObject $logger;

    private SendResetPasswordEmailHandler $handler;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-06 12:00:00');
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->notifier = $this->createMock(UserNotifierInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $this->handler = new SendResetPasswordEmailHandler(
            $this->repository,
            $this->tokenProvider,
            $this->notifier,
            $this->ledger,
            $clock,
            $this->logger,
        );
    }

    public function testItSendsTheResetEmailWithTheCurrentToken(): void
    {
        $event = $this->event();
        $user = $this->userWithResetToken('reset-token', $this->now->modify('+15 minutes'));

        $this->logger->expects($this->never())->method('info');
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->repository->expects($this->once())->method('findById')->willReturn($user);
        $this->tokenProvider->expects($this->once())
            ->method('encode')
            ->with('reset-token', $this->callback(static fn (EmailAddress $email): bool => self::EMAIL === $email->toString()))
            ->willReturn('encoded-token');
        $this->notifier->expects($this->once())
            ->method('sendResetPasswordEmail')
            ->with($user, 'encoded-token');
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), SendResetPasswordEmailHandler::class);

        ($this->handler)($event);
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->logger->expects($this->never())->method('info');
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->repository->expects($this->never())->method('findById');
        $this->tokenProvider->expects($this->never())->method('encode');
        $this->notifier->expects($this->never())->method('sendResetPasswordEmail');

        ($this->handler)($this->event());
    }

    public function testItMarksTheEventAsProcessedWhenTheUserDisappeared(): void
    {
        $event = $this->event();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->repository->expects($this->once())->method('findById')->willReturn(null);
        $this->tokenProvider->expects($this->never())->method('encode');
        $this->notifier->expects($this->never())->method('sendResetPasswordEmail');
        $this->logger->expects($this->once())->method('info')->with(
            'Password reset email skipped because the user no longer exists.',
            ['user_id' => self::USER_ID, 'event_id' => $event->eventId(), 'reason' => 'user_not_found'],
        );
        $this->ledger->expects($this->once())->method('markProcessed')
            ->with($event->eventId(), SendResetPasswordEmailHandler::class);

        ($this->handler)($event);
    }

    public function testItMarksTheEventAsProcessedWhenTheTokenIsGone(): void
    {
        $event = $this->event();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->repository->expects($this->once())->method('findById')->willReturn($this->user());
        $this->tokenProvider->expects($this->never())->method('encode');
        $this->notifier->expects($this->never())->method('sendResetPasswordEmail');
        $this->logger->expects($this->once())->method('info')->with(
            'Password reset email skipped because its token is stale.',
            ['user_id' => self::USER_ID, 'event_id' => $event->eventId(), 'reason' => 'token_missing'],
        );
        $this->ledger->expects($this->once())->method('markProcessed')
            ->with($event->eventId(), SendResetPasswordEmailHandler::class);

        ($this->handler)($event);
    }

    public function testItMarksTheEventAsProcessedWhenTheTokenExpired(): void
    {
        $event = $this->event();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->repository->expects($this->once())->method('findById')
            ->willReturn($this->userWithResetToken('stale-token', $this->now->modify('-1 minute')));
        $this->tokenProvider->expects($this->never())->method('encode');
        $this->notifier->expects($this->never())->method('sendResetPasswordEmail');
        $this->logger->expects($this->once())->method('info')->with(
            'Password reset email skipped because its token is stale.',
            ['user_id' => self::USER_ID, 'event_id' => $event->eventId(), 'reason' => 'token_expired'],
        );
        $this->ledger->expects($this->once())->method('markProcessed')
            ->with($event->eventId(), SendResetPasswordEmailHandler::class);

        ($this->handler)($event);
    }

    private function event(): PasswordResetRequestedEvent
    {
        return new PasswordResetRequestedEvent(
            UserId::fromString(self::USER_ID),
            EmailAddress::fromString(self::EMAIL),
            $this->now,
        );
    }

    private function user(): User
    {
        return User::register(
            id: UserId::fromString(self::USER_ID),
            username: Username::fromString('john'),
            email: EmailAddress::fromString(self::EMAIL),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray([]),
            now: $this->now,
        );
    }

    private function userWithResetToken(string $token, DateTimeImmutable $expiresAt): User
    {
        $user = $this->user();
        $user->requestPasswordReset($token, $expiresAt, $this->now);

        return $user;
    }
}
