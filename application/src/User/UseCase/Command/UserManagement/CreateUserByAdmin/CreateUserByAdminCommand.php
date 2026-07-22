<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UserManagement\CreateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class CreateUserByAdminCommand implements CommandInterface
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public string $email,
        public string $username,
        public string $plainPassword,
        public array $roles,
        public int $status,
        public ?string $firstname = null,
        public ?string $lastname = null,
    ) {
    }
}
