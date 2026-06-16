<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Uniqueness;

use App\Domain\User\Exception\UserDomainException;

final class UsernameAlreadyUsedException extends UserDomainException
{
    public function __construct(string $message = 'Username is already used.')
    {
        parent::__construct($message);
    }
}
