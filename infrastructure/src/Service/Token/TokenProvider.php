<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

use App\Application\User\Port\TokenProviderInterface;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Infrastructure\Service\User\TokenManager;

final readonly class TokenProvider implements TokenProviderInterface
{
    public function __construct(
        private TokenManager $tokenManager,
    ) {
    }

    public function generateRandomToken(int $length = 64): string
    {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;

        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }

        return implode('', $pieces);
    }

    public function encode(string $token, EmailAddress $email): string
    {
        return $this->tokenManager->generateEmailToken($token, $email->toString());
    }

    public function split(string $encodedToken): array
    {
        return $this->tokenManager->splitToken($encodedToken);
    }
}
