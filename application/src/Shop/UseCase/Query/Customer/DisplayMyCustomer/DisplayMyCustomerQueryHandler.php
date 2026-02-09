<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;

final readonly class DisplayMyCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayMyCustomerQuery $query): DisplayMyCustomerOutput
    {
        $customer = $this->repository->findByUserAccountId($query->userAccountId);

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        return new DisplayMyCustomerOutput(
            customerId: $customer->getId(),
            status: $customer->getStatus(),
        );
    }
}
