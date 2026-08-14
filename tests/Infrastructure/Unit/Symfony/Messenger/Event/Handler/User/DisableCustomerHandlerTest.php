<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Http\ShopService\ShopCustomerClientInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Messenger\Event\Handler\User\DisableCustomerHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DisableCustomerHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private ShopCustomerClientInterface&MockObject $shopCustomerClient;

    private DomainEventLedgerInterface&MockObject $ledger;

    private DisableCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->shopCustomerClient = $this->createMock(ShopCustomerClientInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);

        $this->handler = new DisableCustomerHandler($this->shopCustomerClient, $this->ledger);
    }

    /**
     * Le handler passe le compte, pas le client : il n'a plus de dépôt pour traduire l'un en
     * l'autre, et le cas « aucun client rattaché » est traité par le service distant.
     */
    public function testItDisablesTheCustomerOfTheDeletedUser(): void
    {
        $event = $this->deletedEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->shopCustomerClient->expects($this->once())
            ->method('disableCustomer')
            ->with(self::USER_ID);
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), DisableCustomerHandler::class);

        ($this->handler)($event);
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->shopCustomerClient->expects($this->never())->method('disableCustomer');
        $this->ledger->expects($this->never())->method('markProcessed');

        ($this->handler)($this->deletedEvent());
    }

    public function testItLeavesTheEventUnmarkedWhenTheCallFails(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->shopCustomerClient->expects($this->once())
            ->method('disableCustomer')
            ->willThrowException(new RuntimeException('shop service unreachable'));
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->expectException(RuntimeException::class);

        ($this->handler)($this->deletedEvent());
    }

    private function deletedEvent(): UserDeletedByAdminEvent
    {
        return new UserDeletedByAdminEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
