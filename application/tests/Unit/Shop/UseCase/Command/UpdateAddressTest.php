<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\UpdateAddress\UpdateAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\UpdateAddress\UpdateAddressCommandHandler;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440060';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440061';

    private const string OTHER_CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440062';

    private AddressRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UpdateAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new UpdateAddressCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleUpdatesAddress(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-02 10:00:00');
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

        $command = new UpdateAddressCommand(
            addressId: $addressId,
            ownerId: $customerId,
            label: 'Office',
            firstname: 'Jane',
            lastname: 'Doe',
            company: null,
            street: '45 Second St',
            zipCode: '54321',
            city: 'Lyon',
            country: 'France',
            phone: '+33 6 12 34 56 78',
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($updatedAt);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Address $saved) use ($addressId, $customerId, $createdAt, $updatedAt): bool {
                return $saved->getId()->equals($addressId)
                    && $saved->getOwnerId()->equals($customerId)
                    && 'Office' === $saved->getLabel()
                    && 'Jane' === $saved->getFirstname()
                    && 'Doe' === $saved->getLastname()
                    && null === $saved->getCompany()
                    && '45 Second St' === $saved->getStreet()
                    && '54321' === $saved->getZipCode()
                    && 'Lyon' === $saved->getCity()
                    && 'France' === $saved->getCountry()
                    && '+33 6 12 34 56 78' === $saved->getPhone()
                    && $saved->getCreatedAt() === $createdAt
                    && $saved->getUpdatedAt() === $updatedAt;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertSame($address, $output->addressItem->address);
    }

    public function testHandleUpdatesOnlyProvidedFields(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-02 10:00:00');
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

        $command = new UpdateAddressCommand(
            addressId: $addressId,
            ownerId: $customerId,
            label: 'Office',
            firstname: null,
            lastname: null,
            company: null,
            street: null,
            zipCode: null,
            city: null,
            country: null,
            phone: null,
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($updatedAt);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Address $saved) use ($updatedAt): bool {
                return 'Office' === $saved->getLabel()
                    && 'John' === $saved->getFirstname()
                    && 'Doe' === $saved->getLastname()
                    && '12 Main St' === $saved->getStreet()
                    && '12345' === $saved->getZipCode()
                    && 'Paris' === $saved->getCity()
                    && 'France' === $saved->getCountry()
                    && '+33 1 23 45 67 89' === $saved->getPhone()
                    && $saved->getUpdatedAt() === $updatedAt;
            }));

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

        $command = new UpdateAddressCommand(
            addressId: $addressId,
            ownerId: $customerId,
            label: 'Office',
            firstname: 'Jane',
            lastname: 'Doe',
            company: null,
            street: '45 Second St',
            zipCode: '54321',
            city: 'Lyon',
            country: 'France',
            phone: '+33 6 12 34 56 78',
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('save');

        $this->clock->expects($this->never())
            ->method('now');

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

        $command = new UpdateAddressCommand(
            addressId: $addressId,
            ownerId: $customerId,
            label: 'Office',
            firstname: 'Jane',
            lastname: 'Doe',
            company: null,
            street: '45 Second St',
            zipCode: '54321',
            city: 'Lyon',
            country: 'France',
            phone: '+33 6 12 34 56 78',
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->repository->expects($this->never())
            ->method('save');

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle($command);
    }
}
