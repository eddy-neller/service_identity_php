<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\UpdateAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;

final readonly class UpdateAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateAddressCommand $command): UpdateAddressOutput
    {
        $address = $this->repository->findById($command->addressId);

        if (null === $address || !$address->belongsTo($command->ownerId)) {
            throw new AddressNotFoundException();
        }

        $this->transactional->transactional(function () use ($address, $command): void {
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
        });

        return new UpdateAddressOutput(new AddressItem($address));
    }
}
