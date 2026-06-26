<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Customer;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress\SetDefaultAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress\SetDefaultAddressCommandHandler;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetDefaultAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440080';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440081';

    private const string OTHER_CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440082';

    private AddressRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private SetDefaultAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new SetDefaultAddressCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleSetsAddressAsDefault(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2025-01-02 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $address = $this->createAddress($addressId, $customerId, $createdAt);

        $command = new SetDefaultAddressCommand($addressId, $customerId);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($updatedAt);

        $this->repository->expects($this->once())
            ->method('unsetDefaultForOwner')
            ->with($customerId);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Address $saved) use ($addressId, $updatedAt): bool {
                return $saved->getId()->equals($addressId)
                    && $saved->isDefault()
                    && $saved->getUpdatedAt() === $updatedAt;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertSame($address->getId()->toString(), $output->id);
        $this->assertTrue($output->isDefault);
    }

    public function testHandleReturnsAlreadyDefaultAddressWithoutWriting(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $address = $this->createAddress($addressId, $customerId, $createdAt, true);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->repository->expects($this->never())
            ->method('unsetDefaultForOwner');

        $this->repository->expects($this->never())
            ->method('save');

        $this->clock->expects($this->never())
            ->method('now');

        $output = $this->handler->handle(new SetDefaultAddressCommand($addressId, $customerId));

        $this->assertSame($address->getId()->toString(), $output->id);
        $this->assertTrue($output->isDefault);
    }

    public function testHandleThrowsWhenAddressMissing(): void
    {
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn(null);

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle(new SetDefaultAddressCommand($addressId, $customerId));
    }

    public function testHandleThrowsWhenAddressOwnedByAnotherCustomer(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $otherCustomerId = CustomerId::fromString(self::OTHER_CUSTOMER_ID);
        $address = $this->createAddress($addressId, $otherCustomerId, $createdAt);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($addressId)
            ->willReturn($address);

        $this->clock->expects($this->never())
            ->method('now');

        $this->transactional->expects($this->never())
            ->method('transactional');

        $this->expectException(AddressNotFoundException::class);

        $this->handler->handle(new SetDefaultAddressCommand($addressId, $customerId));
    }

    private function createAddress(
        AddressId $addressId,
        CustomerId $customerId,
        DateTimeImmutable $now,
        bool $isDefault = false,
    ): Address {
        return Address::create(
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
            now: $now,
            isDefault: $isDefault,
        );
    }
}
