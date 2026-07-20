<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class InvalidRoleException extends UserDomainException implements InvalidArgumentInterface
{
    public static function invalid(): self
    {
        return new self('Invalid role.');
    }

    public static function notAllowed(string $role): self
    {
        return new self('Role is not allowed: ' . $role);
    }
}
