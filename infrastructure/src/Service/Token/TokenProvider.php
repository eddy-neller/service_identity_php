<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

use App\Application\User\Port\TokenProviderInterface;
use App\Domain\User\ValueObject\EmailAddress;

final readonly class TokenProvider implements TokenProviderInterface
{
    public const string TOKEN_SEPARATOR = '&';

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
        return base64_encode($email->toString() . self::TOKEN_SEPARATOR . $token);
    }

    public function split(string $encodedToken): array
    {
        $decodedToken = base64_decode($encodedToken);
        $email = strtok($decodedToken, self::TOKEN_SEPARATOR);
        $token = substr($decodedToken, strpos($decodedToken, self::TOKEN_SEPARATOR) + 1);

        return ['email' => $email, 'token' => $token];
    }
}
