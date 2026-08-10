<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

final class InvalidCredentialsException extends SecurityDomainException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }
}
