<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayListCategory;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryList;

final readonly class DisplayListCategoryQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListCategoryQuery $query): CategoryList
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
