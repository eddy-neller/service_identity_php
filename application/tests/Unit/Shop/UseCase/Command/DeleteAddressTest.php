<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command;

use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\DeleteAddress\DeleteAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\DeleteAddress\DeleteAddressCommandHandler;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440070';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440071';

    private const string OTHER_CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440072';

    private AddressRepositoryInterface&MockObject $repository;

    private TransactionalInterface&MockObject $transactional;

    private DeleteAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new DeleteAddressCommandHandler(
            $this->repository,
            $this->transactional,
        );
    }

    public function testHandleDeletesAddress(): void
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

        $command = new DeleteAddressCommand($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($address);

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandleThrowsWhenAddressMissing(): void
    {
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $command = new DeleteAddressCommand($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('delete');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($command);
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

        $command = new DeleteAddressCommand($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->never())
            ->method('delete');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($command);
    }
}
