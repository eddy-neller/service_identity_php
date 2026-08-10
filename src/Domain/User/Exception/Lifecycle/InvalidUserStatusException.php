<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Lifecycle;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;
use App\Domain\User\Exception\UserDomainException;

final class InvalidUserStatusException extends UserDomainException implements InvalidArgumentInterface
{
    public static function unsupported(int $value): self
    {
        return new self(sprintf('Unsupported status: %d.', $value));
    }
}
