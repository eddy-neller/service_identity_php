<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;

final readonly class SetDefaultAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(SetDefaultAddressCommand $command): AddressItem
    {
        $address = $this->repository->findById($command->addressId);

        if (null === $address || !$address->belongsTo($command->ownerId)) {
            throw new AddressNotFoundException();
        }

        if ($address->isDefault()) {
            return AddressItem::fromAddress($address);
        }

        $this->transactional->transactional(function () use ($address, $command): void {
            $this->repository->unsetDefaultForOwner($command->ownerId);
            $address->markAsDefault($this->clock->now());
            $this->repository->save($address);
        });

        return AddressItem::fromAddress($address);
    }
}
