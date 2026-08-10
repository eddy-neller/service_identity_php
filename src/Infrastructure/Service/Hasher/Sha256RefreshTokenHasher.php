<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Hasher;

use App\Application\User\Port\RefreshTokenHasherInterface;

final readonly class Sha256RefreshTokenHasher implements RefreshTokenHasherInterface
{
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
