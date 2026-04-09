<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\RateLimit;

use App\Domain\User\Exception\UserDomainException;

final class ResetPasswordLimitReachedException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('Nombre maximal d\'emails de réinitialisation atteint.');
    }
}
