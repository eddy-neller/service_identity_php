<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Auth\RefreshToken;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class RefreshTokenCommand implements CommandInterface
{
    public function __construct(
        public string $refreshToken,
    ) {
    }
}
