<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Domain\Shop\Customer\Exception\CustomerDomainException;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;

final readonly class DisableCustomerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(DisableCustomerCommand $command): DisableCustomerOutput
    {
        $customer = $this->repository->findById($command->customerId);

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        if (null === $customer->getUserAccountId()) {
            throw new CustomerDomainException('Customer has no user account linked.');
        }

        $this->transactional->transactional(function () use ($customer): void {
            $customer->disable($this->clock->now());

            $this->repository->save($customer);
        });

        return new DisableCustomerOutput($customer);
    }
}
