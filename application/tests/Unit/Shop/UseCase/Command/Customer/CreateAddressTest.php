<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Customer;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressCommand;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressCommandHandler;
use App\Domain\Shop\Customer\Exception\AddressLimitReachedException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateAddressTest extends TestCase
{
    private const string ADDRESS_ID = '550e8400-e29b-41d4-a716-446655440050';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440051';

    private AddressRepositoryInterface&MockObject $repository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private CreateAddressCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AddressRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);
        $this->handler = new CreateAddressCommandHandler(
            $this->repository,
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleCreatesAddress(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);

        $command = new CreateAddressCommand(
            ownerId: $customerId,
            label: 'Home',
            firstname: 'John',
            lastname: 'Doe',
            company: 'ACME',
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
        );

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->repository->expects($this->once())
            ->method('countByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(4);

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($addressId);

        $this->repository->expects($this->once())
            ->method('hasDefaultForOwner')
            ->with($customerId)
            ->willReturn(false);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Address $address) use ($addressId, $customerId, $now): bool {
                return $address->getId()->equals($addressId)
                    && $address->getOwnerId()->equals($customerId)
                    && 'Home' === $address->getLabel()
                    && 'John' === $address->getFirstname()
                    && 'Doe' === $address->getLastname()
                    && 'ACME' === $address->getCompany()
                    && '12 Main St' === $address->getStreet()
                    && '12345' === $address->getZipCode()
                    && 'Paris' === $address->getCity()
                    && 'France' === $address->getCountry()
                    && '+33 1 23 45 67 89' === $address->getPhone()
                    && $address->isDefault()
                    && $address->getCreatedAt() === $now
                    && $address->getUpdatedAt() === $now;
            }));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $output = $this->handler->handle($command);

        $this->assertTrue($output->addressItem->address->getId()->equals($addressId));
    }

    public function testHandleCreatesAddressWithoutDefaultWhenOwnerAlreadyHasOne(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $addressId = AddressId::fromString(self::ADDRESS_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);

        $command = new CreateAddressCommand(
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            company: null,
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
        );

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->repository->expects($this->once())
            ->method('countByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(1);

        $this->repository->expects($this->once())
            ->method('nextIdentity')
            ->willReturn($addressId);

        $this->repository->expects($this->once())
            ->method('hasDefaultForOwner')
            ->with($customerId)
            ->willReturn(true);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Address $address): bool => !$address->isDefault()));

        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback();
            });

        $this->handler->handle($command);
    }

    public function testHandleThrowsWhenOwnerAlreadyHasFiveAddresses(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $command = new CreateAddressCommand(
            ownerId: $customerId,
            label: 'Office',
            firstname: 'John',
            lastname: 'Doe',
            company: null,
            street: '12 Main St',
            zipCode: '12345',
            city: 'Paris',
            country: 'France',
            phone: '+33 1 23 45 67 89',
        );

        $this->repository->expects($this->once())
            ->method('countByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(Customer::MAX_ADDRESSES);

        $this->clock->expects($this->never())->method('now');
        $this->repository->expects($this->never())->method('nextIdentity');
        $this->repository->expects($this->never())->method('hasDefaultForOwner');
        $this->repository->expects($this->never())->method('save');
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $this->expectException(AddressLimitReachedException::class);
        $this->expectExceptionMessage('A customer cannot have more than 5 addresses.');

        $this->handler->handle($command);
    }
}
