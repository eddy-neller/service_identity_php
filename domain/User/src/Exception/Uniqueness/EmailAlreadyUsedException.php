<?php

declare(strict_types=1);

namespace App\Domain\User\Exception\Uniqueness;

use App\Domain\User\Exception\UserDomainException;

final class EmailAlreadyUsedException extends UserDomainException
{
    public function __construct(string $message = 'Adresse email déjà utilisée.')
    {
        parent::__construct($message);
    }
}
