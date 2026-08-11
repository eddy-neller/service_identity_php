<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messenger\Event\Handler\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Domain\Shop\Customer\Exception\CustomerAlreadyExistsException;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Symfony\Messenger\Event\Handler\User\ProvisionCustomerHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProvisionCustomerHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private CommandBusInterface&MockObject $commandBus;

    private DomainEventLedgerInterface&MockObject $ledger;

    private ProvisionCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);

        $transactional = $this->createStub(TransactionalInterface::class);
        $transactional->method('transactional')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());

        $this->handler = new ProvisionCustomerHandler(
            $this->commandBus,
            $this->ledger,
            $transactional,
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testItProvisionsTheCustomerOnRegistration(): void
    {
        $event = $this->registeredEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (CreateCustomerCommand $command): bool => self::USER_ID === $command->userAccountId));
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
        $this->commandBus->expects($this->once())->method('dispatch');
        $this->ledger->expects($this->once())->method('markProcessed');

        $this->handler->onUserCreatedByAdmin($event);
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->commandBus->expects($this->never())->method('dispatch');
        $this->ledger->expects($this->never())->method('markProcessed');

        $this->handler->onUserRegistered($this->registeredEvent());
    }

    /**
     * Création concurrente ou redélivrance avant marquage : le Customer existe déjà, la
     * réaction est donc satisfaite. Le 409 porté par la commande reste intact pour l'API.
     */
    public function testItTreatsAnExistingCustomerAsSuccess(): void
    {
        $event = $this->registeredEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->commandBus->expects($this->once())->method('dispatch')->willThrowException(new CustomerAlreadyExistsException());
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), ProvisionCustomerHandler::class);

        $this->handler->onUserRegistered($event);
    }

    private function registeredEvent(): UserRegisteredEvent
    {
        return new UserRegisteredEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }
}
