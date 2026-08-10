<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use InvalidArgumentException;

final class PaymentSessionId
{
    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Payment session id cannot be empty.');
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
