<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CustomerRepositoryInterface;

final readonly class DisplayListCustomerQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListCustomerQuery $query): DisplayListCustomerOutput
    {
        $orderBy = [] !== $query->orderBy ? $query->orderBy : ['createdAt' => 'DESC'];

        $list = $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $query->pagination->page,
            itemsPerPage: $query->pagination->itemsPerPage,
        );

        return new DisplayListCustomerOutput(
            customers: $list->customers,
            totalItems: $list->totalItems,
            totalPages: $list->totalPages,
        );
    }
}
