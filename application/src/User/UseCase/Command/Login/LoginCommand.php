<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Login;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class LoginCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
