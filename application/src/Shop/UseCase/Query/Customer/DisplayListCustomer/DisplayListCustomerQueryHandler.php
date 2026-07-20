<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\CustomerRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\CustomerList;

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

        return $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }
}
