<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Profile;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class InvalidLastnameException extends ProfileDomainException implements InvalidArgumentInterface
{
    public static function empty(): self
    {
        return new self('Last name cannot be empty.');
    }

    public static function tooShort(int $min): self
    {
        return new self(sprintf('Last name must contain at least %d characters.', $min));
    }

    public static function tooLong(int $max): self
    {
        return new self(sprintf('Last name cannot exceed %d characters.', $max));
    }
}
