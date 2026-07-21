<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CustomerItem;
use App\Application\Shop\ReadModel\Customer\CustomerList;
use App\Domain\Shop\Customer\Model\Customer;

final readonly class DisplayListCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListCustomerQuery $query): CustomerList
    {
        $orderBy = [] !== $query->orderBy ? $query->orderBy : ['createdAt' => 'DESC'];
        $pagination = Pagination::fromRaw($query->page, $query->itemsPerPage);

        $result = $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );

        return new CustomerList(
            items: array_map(static fn (Customer $customer): CustomerItem => CustomerItem::fromCustomer($customer), $result['items']),
            totalItems: $result['totalItems'],
            totalPages: $result['totalPages'],
        );
    }
}
