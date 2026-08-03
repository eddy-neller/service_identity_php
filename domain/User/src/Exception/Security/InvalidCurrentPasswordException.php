<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class InvalidCurrentPasswordException extends SecurityDomainException implements InvalidArgumentInterface
{
    public function __construct()
    {
        parent::__construct('The current password is invalid.');
    }
}
