<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Customer;

use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer\DisplayListCustomerQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer\DisplayListCustomerQueryHandler;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayListCustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440110';

    private const string USER_ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440111';

    private CustomerRepositoryInterface&MockObject $repository;

    private DisplayListCustomerQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CustomerRepositoryInterface::class);
        $this->handler = new DisplayListCustomerQueryHandler($this->repository);
    }

    public function testHandleReturnsCustomersAndPagination(): void
    {
        $query = new DisplayListCustomerQuery(
            page: '2',
            itemsPerPage: '5',
            filters: ['status' => 1],
            orderBy: ['createdAt' => 'ASC'],
        );

        $customer = $this->createCustomer();
        $customerItem = CustomerItem::fromCustomer($customer);

        $this->repository->expects($this->once())
            ->method('list')
            ->with(['status' => 1], ['createdAt' => 'ASC'], 2, 5)
            ->willReturn(['items' => [$customer], 'totalItems' => 10, 'totalPages' => 2]);

        $output = $this->handler->handle($query);

        $this->assertEquals([$customerItem], $output->items);
        $this->assertSame(10, $output->totalItems);
        $this->assertSame(2, $output->totalPages);
    }

    public function testHandleAppliesDefaultOrderWhenMissing(): void
    {
        $query = new DisplayListCustomerQuery(
            page: '0',
            itemsPerPage: '0',
            filters: [],
            orderBy: [],
        );

        $customer = $this->createCustomer();
        $customerItem = CustomerItem::fromCustomer($customer);

        $this->repository->expects($this->once())
            ->method('list')
            ->with([], ['createdAt' => 'DESC'], 1, 30)
            ->willReturn(['items' => [$customer], 'totalItems' => 1, 'totalPages' => 1]);

        $output = $this->handler->handle($query);

        $this->assertEquals([$customerItem], $output->items);
        $this->assertSame(1, $output->totalItems);
        $this->assertSame(1, $output->totalPages);
    }

    private function createCustomer(): Customer
    {
        return Customer::create(
            id: CustomerId::fromString(self::CUSTOMER_ID),
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
            userAccountId: UserAccountId::fromString(self::USER_ACCOUNT_ID),
        );
    }
}
