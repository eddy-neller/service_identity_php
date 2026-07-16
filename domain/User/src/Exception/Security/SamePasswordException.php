<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\User\Exception\UserDomainException;

final class SamePasswordException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('The new password must be different from the current password.');
    }
}
