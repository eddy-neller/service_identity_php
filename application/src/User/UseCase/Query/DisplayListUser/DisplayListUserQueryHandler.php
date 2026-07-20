<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\DisplayListUser;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserList;

final readonly class DisplayListUserQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListUserQuery $query): UserList
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
