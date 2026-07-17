<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Domain\Shop\Customer\Exception\CustomerDomainException;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisableCustomerCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(DisableCustomerCommand $command): CustomerItem
    {
        $customerId = CustomerId::fromString($command->customerId);

        $customer = $this->transactional->transactional(function () use ($customerId): Customer {
            $customer = $this->repository->findById($customerId);

            if (null === $customer) {
                throw new CustomerNotFoundException();
            }

            if (null === $customer->getUserAccountId()) {
                throw new CustomerDomainException('Customer has no user account linked.');
            }

            $customer->disable($this->clock->now());

            $this->repository->save($customer);

            return $customer;
        });

        return CustomerItem::fromCustomer($customer);
    }
}
