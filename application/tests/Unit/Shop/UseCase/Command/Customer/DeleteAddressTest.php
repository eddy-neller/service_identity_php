<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Customer;

use App\Application\Shared\Port\ClockInterface;
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

    private ClockInterface&MockObject $clock;

    private DeleteAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->handler = new DeleteAddressCommandHandler(
            $this->repository,
            $this->transactional,
            $this->clock,
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

        $command = new DeleteAddressCommand($addressId->toString(), $customerId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($address);

        $this->repository->expects($this->never())
            ->method('findDefaultReplacementForOwner');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandlePromotesReplacementWhenDeletingDefaultAddress(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-02 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $replacementId = AddressId::fromString('550e8400-e29b-41d4-a716-446655440073');
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
            isDefault: true,
        );
        $replacement = Address::create(
            id: $replacementId,
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            street: '45 Second St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
            now: $createdAt,
        );

        $command = new DeleteAddressCommand($addressId->toString(), $customerId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($address);

        $this->repository->expects($this->once())
            ->method('findDefaultReplacementForOwner')
            ->with($customerId, $addressId)
            ->willReturn($replacement);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($updatedAt);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Address $saved) use ($replacementId, $updatedAt): bool {
                return $saved->getId()->equals($replacementId)
                    && $saved->isDefault()
                    && $saved->getUpdatedAt() === $updatedAt;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandleDeletesDefaultAddressWithNoReplacement(): void
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
            isDefault: true,
        );

        $command = new DeleteAddressCommand($addressId->toString(), $customerId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with($address);

        $this->repository->expects($this->once())
            ->method('findDefaultReplacementForOwner')
            ->with($customerId, $addressId)
            ->willReturn(null);

        $this->clock->expects($this->never())
            ->method('now');

        $this->repository->expects($this->never())
            ->method('save');

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
        $command = new DeleteAddressCommand($addressId->toString(), $customerId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('delete');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

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

        $command = new DeleteAddressCommand($addressId->toString(), $customerId->toString());

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->never())
            ->method('delete');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($command);
    }
}
