<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject\Security;

use App\Domain\SharedKernel\ValueObject\Uuid;

final readonly class RefreshTokenId
{
    private function __construct(
        private Uuid $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(Uuid::fromString($value, 'RefreshTokenId'));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }
}
