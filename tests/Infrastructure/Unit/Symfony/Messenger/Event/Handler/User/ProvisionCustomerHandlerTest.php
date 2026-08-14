<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Http\ShopService\ShopCustomerClientInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Messenger\Event\Handler\User\ProvisionCustomerHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProvisionCustomerHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private ShopCustomerClientInterface&MockObject $shopCustomerClient;

    private DomainEventLedgerInterface&MockObject $ledger;

    private ProvisionCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->shopCustomerClient = $this->createMock(ShopCustomerClientInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);

        $this->handler = new ProvisionCustomerHandler($this->shopCustomerClient, $this->ledger);
    }

    public function testItProvisionsTheCustomerOnRegistration(): void
    {
        $event = $this->registeredEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->shopCustomerClient->expects($this->once())
            ->method('provisionCustomer')
            ->with(self::USER_ID);
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), ProvisionCustomerHandler::class);

        $this->handler->onUserRegistered($event);
    }

    public function testItProvisionsTheCustomerOnAdminCreation(): void
    {
        $event = new UserCreatedByAdminEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->shopCustomerClient->expects($this->once())
            ->method('provisionCustomer')
            ->with(self::USER_ID);
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), ProvisionCustomerHandler::class);

        $this->handler->onUserCreatedByAdmin($event);
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->shopCustomerClient->expects($this->never())->method('provisionCustomer');
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->handler->onUserRegistered($this->registeredEvent());
    }

    /**
     * L'effet étant externe, le marquage n'a lieu qu'au retour de l'appel : un transport en échec
     * laisse l'événement non marqué, donc rejouable par Messenger. Inverser les deux lignes ferait
     * disparaître la réaction en silence — c'est cet ordre que le test tient.
     */
    public function testItLeavesTheEventUnmarkedWhenTheCallFails(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->shopCustomerClient->expects($this->once())
            ->method('provisionCustomer')
            ->willThrowException(new RuntimeException('shop service unreachable'));
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->expectException(RuntimeException::class);

        $this->handler->onUserRegistered($this->registeredEvent());
    }

    private function registeredEvent(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
