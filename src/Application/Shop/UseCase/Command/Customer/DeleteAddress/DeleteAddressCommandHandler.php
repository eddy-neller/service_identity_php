<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DeleteAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DeleteAddressCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
        private TransactionalInterface $transactional,
        private ClockInterface $clock,
    ) {
    }

    public function handle(DeleteAddressCommand $command): void
    {
        $addressId = AddressId::fromString($command->addressId);
        $ownerId = CustomerId::fromString($command->ownerId);

        $this->transactional->transactional(function () use ($addressId, $ownerId): void {
            $address = $this->repository->findById($addressId);

            if (null === $address || !$address->belongsTo($ownerId)) {
                throw new AddressNotFoundException();
            }

            $wasDefault = $address->isDefault();

            $this->repository->delete($address);

            if (!$wasDefault) {
                return;
            }

            $replacement = $this->repository->findDefaultReplacementForOwner($ownerId, $addressId);
            if (null === $replacement) {
                return;
            }

            $replacement->markAsDefault($this->clock->now());
            $this->repository->save($replacement);
        });
    }
}
