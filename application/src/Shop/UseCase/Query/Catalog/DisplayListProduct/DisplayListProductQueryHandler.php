<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayListProduct;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\ProductList;

final readonly class DisplayListProductQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ProductRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListProductQuery $query): ProductList
    {
        $orderBy = [] !== $query->orderBy ? $query->orderBy : ['createdAt' => 'DESC'];

        return $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $query->pagination->page,
            itemsPerPage: $query->pagination->itemsPerPage,
        );
    }
}
