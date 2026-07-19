<?php

declare(strict_types=1);

namespace App\Presentation\User\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\User\UseCase\Query\DisplayListUser\DisplayListUserQuery;
use App\Presentation\User\Presenter\UserResourcePresenter;
use Symfony\Component\HttpFoundation\Request;

final readonly class UserAdminCollectionProvider implements ProviderInterface
{
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

        $pagination = Pagination::fromRaw($filters['page'] ?? null, $filters['itemsPerPage'] ?? null);
        $orderBy = is_array($filters['order'] ?? null) ? $filters['order'] : [];

        $output = $this->queryBus->dispatch(new DisplayListUserQuery(
            pagination: $pagination,
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
