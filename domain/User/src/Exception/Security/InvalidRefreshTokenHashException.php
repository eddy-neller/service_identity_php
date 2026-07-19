<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\User\Exception\UserDomainException;

final class InvalidRefreshTokenHashException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('A refresh token hash cannot be empty.');
    }
}
