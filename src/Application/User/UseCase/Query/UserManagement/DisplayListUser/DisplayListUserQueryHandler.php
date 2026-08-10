<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\UserManagement\DisplayListUser;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserItem;
use App\Application\User\ReadModel\UserList;
use App\Domain\User\Model\User;

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

        $result = $this->repository->list(
            filters: $query->filters,
            orderBy: $orderBy,
            page: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );

        return new UserList(
            items: array_map(static fn (User $user): UserItem => UserItem::fromUser($user), $result['items']),
            totalItems: $result['totalItems'],
            totalPages: $result['totalPages'],
        );
    }
}
