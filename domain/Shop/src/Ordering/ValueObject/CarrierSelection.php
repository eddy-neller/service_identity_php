<?php

declare(strict_types=1);

namespace App\Domain\Shop\Ordering\ValueObject;

use App\Domain\Shop\Shared\ValueObject\Money;
use InvalidArgumentException;

final class CarrierSelection
{
    private function __construct(
        private readonly string $name,
        private readonly Money $price,
    ) {
    }

    public static function fromValues(string $name, Money $price): self
    {
        $trimmed = trim($name);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Carrier name cannot be empty.');
        }

        return new self($trimmed, $price);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }
}
