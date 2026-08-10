<?php

declare(strict_types=1);

namespace App\Application\User\Port;

interface RefreshTokenHasherInterface
{
    public function hash(string $token): string;
}
