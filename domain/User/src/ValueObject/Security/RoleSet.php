<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Security;

use App\Domain\User\Exception\InvalidRoleException;

final readonly class RoleSet
{
    public const string ROLE_USER = 'ROLE_USER';

    public const string ROLE_MODERATEUR = 'ROLE_MODERATEUR';

    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    private const array ALLOWED = [
        self::ROLE_USER,
        self::ROLE_MODERATEUR,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /**
     * @var string[]
     */
    private array $roles;

    /**
     * @param string[] $roles
     */
    private function __construct(array $roles)
    {
        if ([] === $roles) {
            $roles = [self::ROLE_USER];
        }

        foreach ($roles as $role) {
            if (!is_string($role) || '' === trim($role)) {
                throw InvalidRoleException::invalid();
            }

            if (!in_array($role, self::ALLOWED, true)) {
                throw InvalidRoleException::notAllowed($role);
            }
        }

        $this->roles = array_values(array_unique($roles));
    }

    /**
     * @param string[] $roles
     */
    public static function fromArray(array $roles): self
    {
        return new self($roles);
    }

    public function all(): array
    {
        return $this->roles;
    }

    public function contains(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function add(string $role): self
    {
        $roles = $this->roles;
        $roles[] = $role;

        return new self($roles);
    }
}
