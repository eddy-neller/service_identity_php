<?php

declare(strict_types=1);

namespace App\Domain\User\Security\ValueObject;

use InvalidArgumentException;

final readonly class HashedPassword
{
    public function __construct(
        private string $value,
    ) {
        if ('' === trim($value)) {
            throw new InvalidArgumentException('Le mot de passe haché ne peut pas être vide.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
