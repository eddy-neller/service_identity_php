<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayListCategory;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;

final readonly class DisplayListCategoryQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListCategoryQuery $query): DisplayListCategoryOutput
    {
        $orderBy = [] !== $query->orderBy ? $query->orderBy : ['createdAt' => 'DESC'];

        $list = $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $query->pagination->page,
            itemsPerPage: $query->pagination->itemsPerPage,
        );

        return new DisplayListCategoryOutput(
            categories: $list->categories,
            totalItems: $list->totalItems,
            totalPages: $list->totalPages,
        );
    }
}
