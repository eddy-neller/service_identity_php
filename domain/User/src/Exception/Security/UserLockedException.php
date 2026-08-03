<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

final class UserLockedException extends SecurityDomainException
{
    public function __construct()
    {
        parent::__construct('The account is locked.');
    }
}
