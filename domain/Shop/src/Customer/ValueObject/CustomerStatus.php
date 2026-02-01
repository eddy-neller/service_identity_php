<?php

declare(strict_types=1);

namespace App\Domain\Shop\Customer\ValueObject;

final class CustomerStatus
{
    public const int ACTIVE = 1;

    public const int DISABLED = 2;

    public function __construct(
        private readonly int $value = self::ACTIVE,
    ) {
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED);
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

    public function isDisabled(): bool
    {
        return self::DISABLED === $this->value;
    }
}
