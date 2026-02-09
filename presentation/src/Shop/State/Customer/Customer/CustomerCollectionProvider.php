<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer\DisplayListCustomerQuery;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use Symfony\Component\HttpFoundation\Request;

final readonly class CustomerCollectionProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CustomerResourcePresenter $customerResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $pagination = Pagination::fromRaw($filters['page'] ?? null, $filters['itemsPerPage'] ?? null);
        $query = new DisplayListCustomerQuery(
            pagination: $pagination,
            filters: $filters,
            orderBy: is_array($filters['order'] ?? null) ? $filters['order'] : [],
        );

        $output = $this->queryBus->dispatch($query);

        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('_total_items', $output->totalItems);
            $request->attributes->set('_total_pages', $output->totalPages);
        }

        $items = [];
        foreach ($output->customers as $customer) {
            $items[] = $this->customerResourcePresenter->toSummaryResource($customer);
        }

        return $items;
    }
}
