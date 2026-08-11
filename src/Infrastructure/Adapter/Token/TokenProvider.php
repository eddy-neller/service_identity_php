<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Token;

use App\Application\User\Port\TokenProviderInterface;
use App\Domain\User\ValueObject\Identity\EmailAddress;

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

    /**
     * Encodage en base64url (RFC 4648 §5) : alphabet `-`/`_` au lieu de `+`/`/`, et sans
     * padding `=`. Le jeton voyage dans l'URL d'un e-mail : aucun de ces caractères n'a
     * donc besoin d'être percent-encodé, ce qui supprime toute étape d'encodage/décodage
     * sur le trajet — et avec elle les jetons corrompus qu'un `%3D` ou un `+` transformé
     * en espace produisait.
     */
    public function encode(string $token, EmailAddress $email): string
    {
        $payload = $email->toString() . self::TOKEN_SEPARATOR . $token;

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    public function split(string $encodedToken): ?array
    {
        // Décodage strict : un caractère hors alphabet fait échouer le décodage au lieu
        // d'être silencieusement ignoré. En mode permissif, un `%3D` resté encodé voyait
        // son `%` sauté mais ses `3` et `D` absorbés comme données : le jeton obtenu était
        // faux d'un caractère et l'erreur remontait en « utilisateur introuvable ».
        $decoded = base64_decode(strtr($encodedToken, '-_', '+/'), true);

        if (false === $decoded) {
            return null;
        }

        $separator = strpos($decoded, self::TOKEN_SEPARATOR);

        if (false === $separator) {
            return null;
        }

        return [
            'email' => substr($decoded, 0, $separator),
            'token' => substr($decoded, $separator + 1),
        ];
    }
}
