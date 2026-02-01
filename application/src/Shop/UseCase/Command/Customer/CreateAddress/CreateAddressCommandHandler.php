<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Domain\Shop\Customer\Model\Address;

final readonly class CreateAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(CreateAddressCommand $command): CreateAddressOutput
    {
        return $this->transactional->transactional(function () use ($command): CreateAddressOutput {
            $now = $this->clock->now();

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
                now: $now,
                company: $command->company,
            );

            $this->repository->save($address);

            return new CreateAddressOutput(new AddressItem($address));
        });
    }
}
