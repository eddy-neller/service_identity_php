<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use InvalidArgumentException;

final class OrderReference
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Order reference cannot be empty.');
        }

        if (strlen($trimmed) > 255) {
            throw new InvalidArgumentException('Order reference is too long.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
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
