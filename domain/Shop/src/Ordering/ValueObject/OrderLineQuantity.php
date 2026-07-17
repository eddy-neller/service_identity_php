<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use InvalidArgumentException;

final readonly class OrderLineQuantity
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Order line quantity must be greater than zero.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
