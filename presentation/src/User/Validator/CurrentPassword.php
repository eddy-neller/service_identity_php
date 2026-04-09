<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute] class CurrentPassword extends Constraint
{
    public string $message = 'Invalid password.';

    public const string INVALID_PASSWORD_ERROR = 'b0c5d78b-ddd2-4b7b-9bde-da84fd585a5d';

    protected const array ERROR_NAMES = [
        self::INVALID_PASSWORD_ERROR => 'INVALID_PASSWORD_ERROR',
    ];
}
