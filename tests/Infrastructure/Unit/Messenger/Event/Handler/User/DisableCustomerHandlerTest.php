<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Messenger\Event\Handler\User;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommand;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Infrastructure\Messenger\Event\DomainEventLedgerInterface;
use App\Infrastructure\Messenger\Event\Handler\User\DisableCustomerHandler;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisableCustomerHandlerTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440001';

    private CustomerRepositoryInterface&MockObject $customerRepository;

    private CommandBusInterface&MockObject $commandBus;

    private DomainEventLedgerInterface&MockObject $ledger;

    private DisableCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->ledger = $this->createMock(DomainEventLedgerInterface::class);

        $transactional = $this->createStub(TransactionalInterface::class);
        $transactional->method('transactional')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());

        $this->handler = new DisableCustomerHandler(
            $this->customerRepository,
            $this->commandBus,
            $this->ledger,
            $transactional,
        );
    }

    public function testItDisablesTheCustomerOfTheDeletedUser(): void
    {
        $event = $this->deletedEvent();

        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->customerRepository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($this->callback(static fn (UserAccountId $id): bool => self::USER_ID === $id->toString()))
            ->willReturn($this->customer());
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (DisableCustomerCommand $command): bool => self::CUSTOMER_ID === $command->customerId));
        $this->ledger->expects($this->once())
            ->method('markProcessed')
            ->with($event->eventId(), DisableCustomerHandler::class);

        ($this->handler)($event);
    }

    public function testItMarksTheEventEvenWithoutCustomer(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(false);
        $this->customerRepository->expects($this->once())->method('findByUserAccountId')->willReturn(null);
        $this->commandBus->expects($this->never())->method('dispatch');
        $this->ledger->expects($this->once())->method('markProcessed');

        ($this->handler)($this->deletedEvent());
    }

    public function testItSkipsAnAlreadyProcessedEvent(): void
    {
        $this->ledger->expects($this->once())->method('hasProcessed')->willReturn(true);
        $this->customerRepository->expects($this->never())->method('findByUserAccountId');
        $this->commandBus->expects($this->never())->method('dispatch');
        $this->ledger->expects($this->never())->method('markProcessed');

        ($this->handler)($this->deletedEvent());
    }

    private function deletedEvent(): UserDeletedByAdminEvent
    {
        return new UserDeletedByAdminEvent(
            UserId::fromString(self::USER_ID),
            new DateTimeImmutable('2026-08-06 12:00:00'),
        );
    }

    private function customer(): Customer
    {
        return Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: new DateTimeImmutable('2026-08-06 12:00:00'),
            userAccountId: UserAccountId::fromString(self::USER_ID),
        );
    }
}
