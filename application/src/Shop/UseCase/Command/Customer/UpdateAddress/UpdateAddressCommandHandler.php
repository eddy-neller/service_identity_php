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
            );

            $this->repository->save($address);
        });

        return new UpdateAddressOutput(new AddressItem($address));
    }
}
