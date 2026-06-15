<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Customer;

use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQueryHandler;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayCustomerTest extends TestCase
{
    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440100';

    private const string USER_ACCOUNT_ID = '550e8400-e29b-41d4-a716-446655440101';

    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440102';

    private CustomerRepositoryInterface&MockObject $customerRepository;

    private AddressRepositoryInterface&MockObject $addressRepository;

    private DisplayCustomerQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->addressRepository = $this->createMock(AddressRepositoryInterface::class);
        $this->handler = new DisplayCustomerQueryHandler($this->customerRepository, $this->addressRepository);
    }

    public function testHandleReturnsCustomerAndAddresses(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $customer = Customer::create(
            id: $customerId,
            now: $now,
            userAccountId: UserAccountId::fromString(self::USER_ACCOUNT_ID),
        );
        $address = Address::create(
            id: AddressId::fromString(self::ADDRESS_ID),
            ownerId: $customerId,
            label: 'Home',
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: $now,
        );

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId)
            ->willReturn($customer);

        $this->addressRepository->expects($this->once())
            ->method('listByOwner')
            ->with(
                $customerId,
                $this->callback(static fn (Pagination $pagination): bool => 1 === $pagination->page && 1000 === $pagination->itemsPerPage),
                ['createdAt' => 'DESC'],
                [],
            )
            ->willReturn(new AddressList([$address], 1, 1));

        $output = $this->handler->handle(new DisplayCustomerQuery($customerId));

        $this->assertSame($customer, $output->customerItem->customer);
        $this->assertSame([$address], $output->customerItem->addresses);
    }

    public function testHandleThrowsWhenCustomerMissing(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);

        $this->customerRepository->expects($this->once())
            ->method('findById')
            ->with($customerId)
            ->willReturn(null);

        $this->addressRepository->expects($this->never())
            ->method('listByOwner');

        $this->expectException(CustomerNotFoundException::class);

        $this->handler->handle(new DisplayCustomerQuery($customerId));
    }
}
