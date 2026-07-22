<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\RegisterWrongPasswordAttempt;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class RegisterWrongPasswordAttemptCommand implements CommandInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
