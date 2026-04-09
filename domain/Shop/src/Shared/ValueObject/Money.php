<?php

declare(strict_types=1);

namespace App\Domain\Shop\Shared\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $amount,
        private string $currency,
    ) {
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return self::fromInt(0, $currency);
    }

    public static function fromInt(int $amount, string $currency = 'EUR'): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        $normalizedCurrency = strtoupper(trim($currency));

        if ('' === $normalizedCurrency) {
            throw new InvalidArgumentException('Currency cannot be empty.');
        }

        return new self($amount, $normalizedCurrency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Money multiplier must be positive.');
        }

        return new self($this->amount * $multiplier, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money must be in the same currency.');
        }
    }
}
