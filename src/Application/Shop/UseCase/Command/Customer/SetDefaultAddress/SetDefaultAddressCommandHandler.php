<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

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
        $addressId = AddressId::fromString($command->addressId);
        $ownerId = CustomerId::fromString($command->ownerId);

        $address = $this->transactional->transactional(function () use ($addressId, $ownerId): Address {
            $address = $this->repository->findById($addressId);

            if (null === $address || !$address->belongsTo($ownerId)) {
                throw new AddressNotFoundException();
            }

            if ($address->isDefault()) {
                return $address;
            }

            $this->repository->unsetDefaultForOwner($ownerId);
            $address->markAsDefault($this->clock->now());
            $this->repository->save($address);

            return $address;
        });

        return AddressItem::fromAddress($address);
    }
}
