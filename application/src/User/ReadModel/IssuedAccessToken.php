<?php

declare(strict_types=1);

namespace App\Application\User\ReadModel;

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public int $expiresIn,
    ) {
    }
}
