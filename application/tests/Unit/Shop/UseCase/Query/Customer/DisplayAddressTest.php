<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Query\Customer;

use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQueryHandler;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DisplayAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440080';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440081';

    private const string OTHER_CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440082';

    private AddressRepositoryInterface&MockObject $repository;

    private DisplayAddressQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->handler = new DisplayAddressQueryHandler($this->repository);
    }

    public function testHandleReturnsAddressItem(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
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

        $query = new DisplayAddressQuery($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $output = $this->handler->handle($query);

        $this->assertSame($address, $output->addressItem->address);
    }

    public function testHandleThrowsWhenAddressMissing(): void
    {
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $query = new DisplayAddressQuery($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn(null);

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($query);
    }

    public function testHandleThrowsWhenAddressOwnedByAnotherCustomer(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $otherCustomerId = CustomerId::fromString(self::OTHER_CUSTOMER_ID);
        $address = Address::create(
            id: $addressId,
            ownerId: $otherCustomerId,
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

        $query = new DisplayAddressQuery($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($query);
    }
}
