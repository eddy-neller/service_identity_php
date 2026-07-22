<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Identity;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;
use App\Domain\User\Exception\UserDomainException;

final class InvalidEmailAddressException extends UserDomainException implements InvalidArgumentInterface
{
    public static function invalidFormat(): self
    {
        return new self('Email address is invalid.');
    }
}
