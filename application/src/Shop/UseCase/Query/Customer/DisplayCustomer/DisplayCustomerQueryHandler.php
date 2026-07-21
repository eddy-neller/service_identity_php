<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Domain\Shop\Customer\Exception\CustomerNotFoundException;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private AddressRepositoryInterface $addressRepository,
    ) {
    }

    public function handle(DisplayCustomerQuery $query): CustomerItem
    {
        $customer = $this->customerRepository->findById(CustomerId::fromString($query->customerId));

        if (null === $customer) {
            throw new CustomerNotFoundException();
        }

        $result = $this->addressRepository->listByOwner(
            ownerId: $customer->getId(),
            page: 1,
            itemsPerPage: Customer::MAX_ADDRESSES,
            orderBy: ['createdAt' => 'DESC'],
            filters: [],
        );

        return CustomerItem::fromCustomer(
            customer: $customer,
            addresses: array_map(static fn (Address $address): AddressItem => AddressItem::fromAddress($address), $result['items']),
        );
    }
}
