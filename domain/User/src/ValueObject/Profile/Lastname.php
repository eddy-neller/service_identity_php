<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Profile;

use App\Domain\User\Exception\Profile\InvalidLastnameException;

final readonly class Lastname
{
    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 50;

    private string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if (empty($trimmed)) {
            throw InvalidLastnameException::empty();
        }

        $length = mb_strlen($trimmed);
        if ($length < self::MIN_LENGTH) {
            throw InvalidLastnameException::tooShort(self::MIN_LENGTH);
        }

        if ($length > self::MAX_LENGTH) {
            throw InvalidLastnameException::tooLong(self::MAX_LENGTH);
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
