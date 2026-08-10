<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Application\Shared\Port\FileInterface;

interface AvatarStorageInterface
{
    public function store(FileInterface $file): string;

    public function delete(string $fileName): void;
}
