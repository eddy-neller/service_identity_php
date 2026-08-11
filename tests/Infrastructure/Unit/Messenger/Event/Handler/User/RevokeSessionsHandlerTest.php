<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messenger\Event\Handler\User;

use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Domain\User\Event\Security\ReauthenticationReason;
use App\Domain\User\Event\Security\UserReauthenticationRequiredEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Adapter\Token\AuthVersionStoreInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Messenger\Event\Handler\User\RevokeSessionsHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RevokeSessionsHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;

    private AuthVersionStoreInterface&MockObject $authVersionStore;

    private DomainEventLedgerInterface&MockObject $ledger;

    private RevokeSessionsHandler $handler;

    protected function setUp(): void
    {
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->authVersionStore = $this->createMock(AuthVersionStoreInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);

        $this->handler = new RevokeSessionsHandler(
            $this->refreshTokenRepository,
            $this->authVersionStore,
            $this->ledger,
        );
    }

    public function testItRevokesRefreshTokensAndRotatesAuthVersion(): void
    {
        $event = $this->event(ReauthenticationReason::PASSWORD_CHANGED);

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->refreshTokenRepository->expects($this->once())
            ->method('deleteAllForUser')
            ->with($this->callback(static fn (UserId $id): bool => self::USER_ID === $id->toString()));
        $this->authVersionStore->expects($this->once())->method('rotate')->with(self::USER_ID);
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), RevokeSessionsHandler::class);

        ($this->handler)($event);
    }

    /**
     * Un changement de rôles n'invalide pas les sessions : le prochain jeton portera
     * les nouveaux rôles.
     */
    public function testItLeavesSessionsIntactWhenOnlyRolesChanged(): void
    {
        $this->ledger->expects($this->never())->method('hasProcessed');
        $this->refreshTokenRepository->expects($this->never())->method('deleteAllForUser');
        $this->authVersionStore->expects($this->never())->method('rotate');

        ($this->handler)($this->event(ReauthenticationReason::ROLES_CHANGED));
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->refreshTokenRepository->expects($this->never())->method('deleteAllForUser');
        $this->authVersionStore->expects($this->never())->method('rotate');
        $this->ledger->expects($this->never())->method('markProcessed');

        ($this->handler)($this->event(ReauthenticationReason::PASSWORD_CHANGED));
    }

    private function event(ReauthenticationReason $reason): UserReauthenticationRequiredEvent
    {
        return new UserReauthenticationRequiredEvent(
            UserId::fromString(self::USER_ID),
            $reason,
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
