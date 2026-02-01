<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;

final readonly class DisableCustomerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(DisableCustomerCommand $command): void
    {
        $customer = $this->repository->findByUserAccountId($command->userAccountId);

        if (null === $customer) {
            return;
        }

        $this->transactional->transactional(function () use ($customer): void {
            $customer->disable($this->clock->now());

            $this->repository->save($customer);
        });
    }
}
