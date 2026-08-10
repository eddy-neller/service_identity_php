<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\UpdatePassword;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class UpdatePasswordCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
