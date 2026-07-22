<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\RequestPasswordReset;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class RequestPasswordResetCommand implements CommandInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
