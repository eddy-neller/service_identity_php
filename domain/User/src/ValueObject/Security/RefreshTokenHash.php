<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Security;

use InvalidArgumentException;

final readonly class RefreshTokenHash
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException('A refresh token hash cannot be empty.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
