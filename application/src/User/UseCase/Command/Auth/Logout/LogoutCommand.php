<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Auth\Logout;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class LogoutCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $refreshToken,
    ) {
    }
}
