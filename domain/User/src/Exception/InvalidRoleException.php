<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidRoleException extends UserDomainException
{
    public static function invalid(): self
    {
        return new self('Role invalide.');
    }

    public static function notAllowed(string $role): self
    {
        return new self('Role non autorisé: ' . $role);
    }
}
