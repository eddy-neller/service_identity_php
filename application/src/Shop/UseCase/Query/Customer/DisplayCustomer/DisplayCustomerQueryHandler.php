<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;

final readonly class DisplayCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private AddressRepositoryInterface $addressRepository,
    ) {
    }

    public function handle(DisplayCustomerQuery $query): CustomerItem
    {
        $customer = $this->customerRepository->findById($query->customerId);

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        $addressList = $this->addressRepository->listByOwner(
            ownerId: $customer->getId(),
            pagination: Pagination::fromValues(1, 1000),
            orderBy: ['createdAt' => 'DESC'],
            filters: [],
        );

        return CustomerItem::fromCustomer(
            customer: $customer,
            addresses: $addressList->items,
        );
    }
}
