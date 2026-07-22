<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\ValueObject\Identity\EmailAddress;

interface TokenProviderInterface
{
    public function generateRandomToken(): string;

    public function encode(string $token, EmailAddress $email): string;

    public function split(string $encodedToken): array;
}
