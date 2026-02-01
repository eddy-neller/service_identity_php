<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query;

use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQueryHandler;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayCustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440100';
    private const string ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440101';

    private CustomerRepositoryInterface&MockObject $repository;
    private DisplayCustomerQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new DisplayCustomerQueryHandler($this->repository);
    }

    public function testHandleReturnsCustomerData(): void
    {
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);
        $customer = Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
            userAccountId: $accountId,
        );

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn($customer);

        $output = $this->handler->handle(new DisplayCustomerQuery($accountId));

        $this->assertTrue($output->customerId->equals($customer->getId()));
        $this->assertSame($customer->getStatus(), $output->status);
    }

    public function testHandleThrowsWhenCustomerMissing(): void
    {
        $accountId = UserAccountId::fromString(self::ACCOUNT_ID);

        $this->repository->expects($this->once())
            ->method('findByUserAccountId')
            ->with($accountId)
            ->willReturn(null);

        $this->expectException(CustomerNotFoundException::class);

        $this->handler->handle(new DisplayCustomerQuery($accountId));
    }
}
