<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidLastnameException extends UserDomainException
{
    public static function empty(): self
    {
        return new self('Le nom ne peut pas être vide.');
    }

    public static function tooShort(int $min): self
    {
        return new self(sprintf('Le nom doit contenir au moins %d caractères.', $min));
    }

    public static function tooLong(int $max): self
    {
        return new self(sprintf('Le nom ne peut pas dépasser %d caractères.', $max));
    }
}
