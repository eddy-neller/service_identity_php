<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;

final readonly class SetDefaultAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(SetDefaultAddressCommand $command): SetDefaultAddressOutput
    {
        $address = $this->repository->findById($command->addressId);

        if (null === $address || !$address->belongsTo($command->ownerId)) {
            throw new AddressNotFoundException();
        }

        if ($address->isDefault()) {
            return new SetDefaultAddressOutput(new AddressItem($address));
        }

        $this->transactional->transactional(function () use ($address, $command): void {
            $now = $this->clock->now();

            $this->repository->unsetDefaultForOwner($command->ownerId);
            $address->markAsDefault($now);
            $this->repository->save($address);
        });

        return new SetDefaultAddressOutput(new AddressItem($address));
    }
}
