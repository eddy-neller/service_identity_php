<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Customer;

use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQueryHandler;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayListAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440090';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440091';

    private AddressRepositoryInterface&MockObject $repository;

    private DisplayListAddressQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->handler = new DisplayListAddressQueryHandler($this->repository);
    }

    public function testHandleReturnsList(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $pagination = Pagination::fromRaw(1, 10);
        $orderBy = ['createdAt' => 'DESC'];
        $filters = ['city' => 'Paris'];

        $address = Address::create(
            id: $addressId,
            ownerId: $customerId,
            label: 'Home',
            firstname: 'John',
            lastname: 'Doe',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: $createdAt,
        );

        $query = new DisplayListAddressQuery(
            ownerId: $customerId,
            pagination: $pagination,
            orderBy: $orderBy,
            filters: $filters,
        );

        $addressList = new AddressList([$address], 1, 1);

        $this->repository->expects($this->once())
            ->method('listByOwner')
            ->with($customerId, $pagination, $orderBy, $filters)
            ->willReturn($addressList);

        $output = $this->handler->handle($query);

        $this->assertSame([$address], $output->addresses);
        $this->assertSame(1, $output->totalItems);
        $this->assertSame(1, $output->totalPages);
    }
}
