<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Query\Account\DisplayUser;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\Identity\UserId;

final readonly class DisplayUserQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayUserQuery $query): UserItem
    {
        $user = $this->repository->findById(UserId::fromString($query->userId));

        if (null === $user) {
            throw new UserNotFoundException();
        }

        return UserItem::fromUser($user);
    }
}
