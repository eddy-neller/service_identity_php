<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdatePassword;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class UpdatePasswordCommand implements CommandInterface
{
    public function __construct(
        public UserId $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
