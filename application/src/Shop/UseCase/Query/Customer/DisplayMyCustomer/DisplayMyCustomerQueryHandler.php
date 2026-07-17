<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CurrentCustomerItem;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;

final readonly class DisplayMyCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayMyCustomerQuery $query): CurrentCustomerItem
    {
        $customer = $this->repository->findByUserAccountId(UserAccountId::fromString($query->userAccountId));

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        return CurrentCustomerItem::fromCustomer($customer);
    }
}
