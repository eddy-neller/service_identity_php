<?php

declare(strict_types=1);

namespace App\Application\User\ReadModel;

final readonly class AuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,
        public int $expiresIn,
    ) {
    }

    public static function of(IssuedAccessToken $accessToken, string $refreshToken): self
    {
        return new self(
            accessToken: $accessToken->token,
            refreshToken: $refreshToken,
            tokenType: 'Bearer',
            expiresIn: $accessToken->expiresIn,
        );
    }
}
