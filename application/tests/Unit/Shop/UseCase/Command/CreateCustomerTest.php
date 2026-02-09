<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommandHandler;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerOutput;
use App\Domain\Shop\Customer\Exception\CustomerAlreadyExistsException;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateCustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440030';
    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440031';

    private CustomerRepositoryInterface&MockObject $repository;
    private ClockInterface&MockObject $clock;
    private TransactionalInterface&MockObject $transactional;
    private CreateCustomerCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new CreateCustomerCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleCreatesCustomerWhenMissing(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);
        $command = new CreateCustomerCommand($accountId);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($customerId);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Customer $customer) use ($customerId, $accountId, $now): bool {
                return $customer->getId()->equals($customerId)
                    && $customer->getUserAccountId()?->equals($accountId)
                    && $customer->getStatus()->isActive()
                    && $customer->getCreatedAt() === $now
                    && $customer->getUpdatedAt() === $now;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $result = $this->handler->handle($command);

        $this->assertInstanceOf(CreateCustomerOutput::class, $result);
        $this->assertTrue($result->customer->getId()->equals($customerId));
        $this->assertTrue($result->customer->getUserAccountId()?->equals($accountId));
        $this->assertTrue($result->customer->getStatus()->isActive());
    }

    public function testHandleThrowsWhenCustomerAlreadyExistsForUser(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);
        $customer = Customer::create($customerId, $createdAt, $accountId);
        $command = new CreateCustomerCommand($accountId);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn($customer);

        $this->repository->expects($this->never())
            ->method('nextIdentity');

        $this->clock->expects($this->never())
            ->method('now');

        $this->repository->expects($this->never())
            ->method('save');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->expectException(CustomerAlreadyExistsException::class);
        $this->expectExceptionMessage('Customer already exists for this user account.');

        $this->handler->handle($command);
    }
}
