<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateCustomer;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Domain\Shop\Customer\Model\Customer;

final readonly class CreateCustomerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(CreateCustomerCommand $command): void
    {
        $this->transactional->transactional(function () use ($command): void {
            $now = $this->clock->now();
            $customer = $this->repository->findByUserAccountId($command->userAccountId);

            if (null === $customer) {
                $customer = Customer::create(
                    id: $this->repository->nextIdentity(),
                    now: $now,
                    userAccountId: $command->userAccountId,
                );
            } else {
                $customer->activate($now);
            }

            $this->repository->save($customer);
        });
    }
}
