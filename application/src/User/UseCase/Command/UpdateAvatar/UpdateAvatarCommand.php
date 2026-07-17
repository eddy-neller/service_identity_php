<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdateAvatar;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Application\Shared\Port\FileInterface;

final readonly class UpdateAvatarCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public FileInterface $avatarFile,
    ) {
    }
}
