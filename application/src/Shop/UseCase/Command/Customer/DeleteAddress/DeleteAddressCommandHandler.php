<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DeleteAddress;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;

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
        $address = $this->repository->findById($command->addressId);

        if (null === $address || !$address->belongsTo($command->ownerId)) {
            throw new AddressNotFoundException();
        }

        $this->transactional->transactional(function () use ($address, $command): void {
            $wasDefault = $address->isDefault();

            $this->repository->delete($address);

            if (!$wasDefault) {
                return;
            }

            $replacement = $this->repository->findDefaultReplacementForOwner($command->ownerId, $command->addressId);
            if (null === $replacement) {
                return;
            }

            $replacement->markAsDefault($this->clock->now());
            $this->repository->save($replacement);
        });
    }
}
