<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidFirstnameException extends UserDomainException
{
    public static function empty(): self
    {
        return new self('First name cannot be empty.');
    }

    public static function tooShort(int $min): self
    {
        return new self(sprintf('First name must contain at least %d characters.', $min));
    }

    public static function tooLong(int $max): self
    {
        return new self(sprintf('First name cannot exceed %d characters.', $max));
    }
}
