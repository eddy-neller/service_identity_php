<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Application\Shared\Port\FileInterface;

interface AvatarImageValidatorInterface
{
    public function validate(FileInterface $file): void;
}
