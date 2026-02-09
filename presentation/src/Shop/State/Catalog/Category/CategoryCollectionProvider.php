<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Category;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\UseCase\Query\Catalog\DisplayListCategory\DisplayListCategoryQuery;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use Symfony\Component\HttpFoundation\Request;

final readonly class CategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CategoryResourcePresenter $categoryResourcePresenter,
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

        $output = $this->queryBus->dispatch(new DisplayListCategoryQuery(
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
        foreach ($output->categories as $category) {
            $items[] = $this->categoryResourcePresenter->toSummaryResource($category);
        }

        return $items;
    }
}
