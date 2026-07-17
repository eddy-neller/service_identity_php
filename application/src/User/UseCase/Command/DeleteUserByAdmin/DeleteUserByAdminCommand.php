<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\DeleteUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class DeleteUserByAdminCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
