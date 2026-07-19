<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class JwtAccessTokenUser implements UserInterface
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private string $email,
        private array $roles,
    ) {
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
