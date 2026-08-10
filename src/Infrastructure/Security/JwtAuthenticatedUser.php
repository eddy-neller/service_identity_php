<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Deprecated;
use InvalidArgumentException;
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

    public static function createFromPayload($username, array $payload): JWTUserInterface
    {
        if (!is_string($username) || !Uuid::isValid($username)) {
            throw new InvalidArgumentException('JWT subject must be a valid UUID.');
        }

        $roles = $payload['roles'] ?? [];
        if (!is_array($roles)) {
            throw new InvalidArgumentException('JWT roles must be a list of strings.');
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new InvalidArgumentException('JWT roles must be a list of strings.');
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
