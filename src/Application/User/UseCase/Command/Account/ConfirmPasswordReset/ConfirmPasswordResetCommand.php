<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\ConfirmPasswordReset;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class ConfirmPasswordResetCommand implements CommandInterface
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
