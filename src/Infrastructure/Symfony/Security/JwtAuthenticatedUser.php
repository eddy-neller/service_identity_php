<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Security;

use Deprecated;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class JwtAuthenticatedUser implements JWTUserInterface
{
    /**
     * @param list<string> $roles
     */
    private function __construct(
        private UuidInterface $id,
        private array $roles,
    ) {
    }

    /**
     * Un payload mal formé lève une `AuthenticationException`, et rien d'autre. `JWTAuthenticator`
     * ne rattrape pas ce qui sort de cette méthode : seule une exception de sécurité devient un 401.
     * `JwtAuthVersionSubscriber` valide déjà le `sub` en amont, mais pas `roles` — une
     * `InvalidArgumentException` répondait 500 à un token pourtant correctement signé.
     */
    public static function createFromPayload($username, array $payload): JWTUserInterface
    {
        if (!is_string($username) || !Uuid::isValid($username)) {
            throw new InvalidTokenException('JWT subject must be a valid UUID.');
        }

        $roles = $payload['roles'] ?? [];
        if (!is_array($roles)) {
            throw new InvalidTokenException('JWT roles must be a list of strings.');
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new InvalidTokenException('JWT roles must be a list of strings.');
            }
        }

        return new self(Uuid::fromString($username), array_values($roles));
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toString();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    #[Deprecated]
    public function eraseCredentials(): void
    {
    }
}
