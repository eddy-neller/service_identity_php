<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\ResetWrongPasswordAttempts;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class ResetWrongPasswordAttemptsCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
