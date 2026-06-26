<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Customer;

use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQueryHandler;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayMyCustomerTest extends TestCase
{
    private const string USER_ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440070';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440071';

    private CustomerRepositoryInterface&MockObject $repository;

    private DisplayMyCustomerQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new DisplayMyCustomerQueryHandler($this->repository);
    }

    public function testHandleReturnsCustomer(): void
    {
        $userAccountId = UserAccountId::fromString(self::USER_ACCOUNT_ID);
        $customer = Customer::reconstitute(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            status: CustomerStatus::active(),
            createdAt: new DateTimeImmutable('2025-01-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2025-01-02 10:00:00'),
            userAccountId: $userAccountId,
        );

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($userAccountId)
            ->willReturn($customer);

        $output = $this->handler->handle(new DisplayMyCustomerQuery($userAccountId));

        $this->assertSame(self::CUSTOMER_ID, $output->id);
    }

    public function testHandleThrowsWhenCustomerMissing(): void
    {
        $userAccountId = UserAccountId::fromString(self::USER_ACCOUNT_ID);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($userAccountId)
            ->willReturn(null);

        $this->expectException(CustomerNotFoundException::class);

        $this->handler->handle(new DisplayMyCustomerQuery($userAccountId));
    }
}
