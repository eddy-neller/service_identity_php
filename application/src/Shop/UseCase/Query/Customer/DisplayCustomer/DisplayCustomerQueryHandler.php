<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;

final readonly class DisplayCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayCustomerQuery $query): DisplayCustomerOutput
    {
        $customer = $this->repository->findByUserAccountId($query->userAccountId);

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        return new DisplayCustomerOutput(
            customerId: $customer->getId(),
            status: $customer->getStatus(),
        );
    }
}
