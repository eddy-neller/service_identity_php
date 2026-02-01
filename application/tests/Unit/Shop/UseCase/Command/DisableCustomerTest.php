<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommand;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommandHandler;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisableCustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440040';
    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440041';

    private CustomerRepositoryInterface&MockObject $repository;
    private ClockInterface&MockObject $clock;
    private TransactionalInterface&MockObject $transactional;
    private DisableCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new DisableCustomerCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleDoesNothingWhenCustomerMissing(): void
    {
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);
        $command = new DisableCustomerCommand($accountId);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn(null);

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->repository->expects($this->never())
            ->method('save');

        $this->handler->handle($command);
    }

    public function testHandleDisablesCustomer(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $now = new DateTimeImmutable('2025-01-02 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);
        $customer = Customer::create($customerId, $createdAt, $accountId);

        $command = new DisableCustomerCommand($accountId);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn($customer);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Customer $saved) use ($customerId, $accountId, $createdAt, $now): bool {
                return $saved->getId()->equals($customerId)
                    && $saved->getUserAccountId()?->equals($accountId)
                    && $saved->getStatus()->isDisabled()
                    && $saved->getCreatedAt() === $createdAt
                    && $saved->getUpdatedAt() === $now;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }
}
