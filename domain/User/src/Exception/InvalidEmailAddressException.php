<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class InvalidEmailAddressException extends UserDomainException
{
    public static function invalidFormat(): self
    {
        return new self('Email address is invalid.');
    }
}
