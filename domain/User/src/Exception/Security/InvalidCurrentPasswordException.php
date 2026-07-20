<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;
use App\Domain\User\Exception\UserDomainException;

final class InvalidCurrentPasswordException extends UserDomainException implements InvalidArgumentInterface
{
    public function __construct()
    {
        parent::__construct('The current password is invalid.');
    }
}
