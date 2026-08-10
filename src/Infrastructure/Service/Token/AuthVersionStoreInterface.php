<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

interface AuthVersionStoreInterface
{
    public function getOrCreate(string $userId): string;

    public function rotate(string $userId): string;

    public function matches(string $userId, string $authVersion): bool;
}
