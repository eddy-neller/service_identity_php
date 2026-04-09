<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\User;

use App\Infrastructure\Entity\User\User;
use App\Infrastructure\Persistence\Doctrine\User\UserRepository;

class TokenManager
{
    public const string TOKEN_SEPARATOR = '&';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function splitToken(string $encodedToken): array
    {
        $decodedToken = base64_decode($encodedToken);
        $email = strtok($decodedToken, self::TOKEN_SEPARATOR);
        $token = substr($decodedToken, strpos($decodedToken, self::TOKEN_SEPARATOR) + 1);

        return ['email' => $email, 'token' => $token];
    }

    public function generateEmailToken(string $token, string $email): string
    {
        return base64_encode($email . self::TOKEN_SEPARATOR . $token);
    }

    public function retrieveUser(string $field, string $key, string $token): ?User
    {
        return $this->userRepository->findInJsonField($field, $key, $token);
    }

    public function clearJsonField(array $array): array
    {
        foreach (array_keys($array) as $key) {
            $array[$key] = null;
        }

        $array['mailSent'] = 0;

        return $array;
    }
}
