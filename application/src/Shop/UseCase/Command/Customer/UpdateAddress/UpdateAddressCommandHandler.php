<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\UpdateAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class UpdateAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateAddressCommand $command): AddressItem
    {
        $addressId = AddressId::fromString($command->addressId);
        $ownerId = CustomerId::fromString($command->ownerId);

        $address = $this->transactional->transactional(function () use ($addressId, $ownerId, $command): Address {
            $address = $this->repository->findById($addressId);

            if (null === $address || !$address->belongsTo($ownerId)) {
                throw new AddressNotFoundException();
            }

            $address->update(
                label: $command->label ?? $address->getLabel(),
                firstname: $command->firstname ?? $address->getFirstname(),
                lastname: $command->lastname ?? $address->getLastname(),
                street: $command->street ?? $address->getStreet(),
                zipCode: $command->zipCode ?? $address->getZipCode(),
                city: $command->city ?? $address->getCity(),
                country: $command->country ?? $address->getCountry(),
                phone: $command->phone ?? $address->getPhone(),
                now: $this->clock->now(),
                company: $command->company ?? $address->getCompany(),
            );

            $this->repository->save($address);

            return $address;
        });

        return AddressItem::fromAddress($address);
    }
}
