<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressLimitReachedException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\Model\Customer;

final readonly class CreateAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(CreateAddressCommand $command): AddressItem
    {
        return $this->transactional->transactional(function () use ($command): AddressItem {
            if ($this->repository->countByOwnerForUpdate($command->ownerId) >= Customer::MAX_ADDRESSES) {
                throw new AddressLimitReachedException();
            }

            $isDefault = !$this->repository->hasDefaultForOwner($command->ownerId);

            $address = Address::create(
                id: $this->repository->nextIdentity(),
                ownerId: $command->ownerId,
                label: $command->label,
                firstname: $command->firstname,
                lastname: $command->lastname,
                street: $command->street,
                zipCode: $command->zipCode,
                city: $command->city,
                country: $command->country,
                phone: $command->phone,
                now: $this->clock->now(),
                company: $command->company,
                isDefault: $isDefault,
            );

            $this->repository->save($address);

            return AddressItem::fromAddress($address);
        });
    }
}
