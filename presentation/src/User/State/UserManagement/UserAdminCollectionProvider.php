<?php

declare(strict_types=1);

namespace App\Presentation\User\State\UserManagement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Query\UserManagement\DisplayListUser\DisplayListUserQuery;
use App\Presentation\Shared\State\CollectionParameterNormalizerTrait;
use App\Presentation\User\Presenter\UserResourcePresenter;
use Symfony\Component\HttpFoundation\Request;

final readonly class UserAdminCollectionProvider implements ProviderInterface
{
    use CollectionParameterNormalizerTrait;

    public function __construct(
        private QueryBusInterface $queryBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $orderBy = $this->normalizeOrderBy($filters['order'] ?? null, UserRepositoryInterface::SORT_FIELDS);

        $output = $this->queryBus->dispatch(new DisplayListUserQuery(
            page: $this->normalizePaginationParameter($filters['page'] ?? null),
            itemsPerPage: $this->normalizePaginationParameter($filters['itemsPerPage'] ?? null),
            filters: $filters,
            orderBy: $orderBy,
        ));

        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('_total_items', $output->totalItems);
            $request->attributes->set('_total_pages', $output->totalPages);
        }

        $items = [];
        foreach ($output->items as $userItem) {
            $items[] = $this->userResourcePresenter->toResource($userItem);
        }

        return $items;
    }
}
