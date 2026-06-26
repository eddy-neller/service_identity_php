<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressCollectionProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CurrentCustomerResolver $customerResolver,
        private AddressResourcePresenter $presenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $pagination = Pagination::fromRaw($filters['page'] ?? null, $filters['itemsPerPage'] ?? null);
        $orderBy = is_array($filters['order'] ?? null) ? $filters['order'] : [];

        $output = $this->queryBus->dispatch(new DisplayListAddressQuery(
            ownerId: $this->customerResolver->resolve(),
            pagination: $pagination,
            orderBy: $orderBy,
            filters: $filters,
        ));

        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('_total_items', $output->totalItems);
            $request->attributes->set('_total_pages', $output->totalPages);
        }

        $items = [];
        foreach ($output->items as $address) {
            $items[] = $this->presenter->toResource($address);
        }

        return $items;
    }
}
