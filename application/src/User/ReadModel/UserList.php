<?php

declare(strict_types=1);

namespace App\Application\User\ReadModel;

final readonly class UserList
{
    /**
     * @param list<UserItem> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
