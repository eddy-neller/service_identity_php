<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Uniqueness;

use App\Domain\SharedKernel\Exception\ConflictInterface;
use App\Domain\User\Exception\UserDomainException;

final class EmailAlreadyUsedException extends UserDomainException implements ConflictInterface
{
    public function __construct(string $message = 'Email address is already used.')
    {
        parent::__construct($message);
    }
}
