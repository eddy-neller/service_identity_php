<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Presentation\Shared\State\CollectionParameterNormalizerTrait;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressCollectionProvider implements ProviderInterface
{
    use CollectionParameterNormalizerTrait;

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

        $orderBy = $this->normalizeOrderBy($filters['order'] ?? null, AddressRepositoryInterface::SORT_FIELDS);

        $output = $this->queryBus->dispatch(new DisplayListAddressQuery(
            ownerId: $this->customerResolver->resolve(),
            page: $this->normalizePaginationParameter($filters['page'] ?? null),
            itemsPerPage: $this->normalizePaginationParameter($filters['itemsPerPage'] ?? null),
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
