<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Security;

final class UserStatus
{
    public const int INACTIVE = 0;

    public const int ACTIVE = 1;

    public const int BLOCKED = 2;

    public function __construct(
        private readonly int $value = self::INACTIVE,
    ) {
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function blocked(): self
    {
        return new self(self::BLOCKED);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return self::ACTIVE === $this->value;
    }

    public function isBlocked(): bool
    {
        return self::BLOCKED === $this->value;
    }
}
