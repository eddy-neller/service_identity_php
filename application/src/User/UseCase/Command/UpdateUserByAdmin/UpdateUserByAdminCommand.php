<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class UpdateUserByAdminCommand implements CommandInterface
{
    /**
     * @param string[]|null $roles
     */
    public function __construct(
        public string $userId,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $plainPassword = null,
        public ?array $roles = null,
        public ?int $status = null,
        public ?string $firstname = null,
        public ?string $lastname = null,
    ) {
    }
}
