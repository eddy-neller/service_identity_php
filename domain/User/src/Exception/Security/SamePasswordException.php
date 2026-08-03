<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Security;

use App\Domain\SharedKernel\Exception\InvalidArgumentInterface;

final class SamePasswordException extends SecurityDomainException implements InvalidArgumentInterface
{
    public function __construct()
    {
        parent::__construct('The new password must be different from the current password.');
    }
}
