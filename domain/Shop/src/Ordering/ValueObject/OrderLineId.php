<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use App\Domain\SharedKernel\ValueObject\Uuid;

final readonly class OrderLineId
{
    private function __construct(
        private Uuid $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value, 'OrderLineId'));
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function __toString(): string
    {
        return $this->value->toString();
    }
}
