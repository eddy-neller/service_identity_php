<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute] class EmailNotExists extends Constraint
{
    public string $message = 'This email is already registered. Please use a different email or sign in.';

    public const string SAME_EMAIL_ERROR = '4f50affd-e18e-4666-b3d3-bd6519dd6750';

    protected const array ERROR_NAMES = [
        self::SAME_EMAIL_ERROR => 'SAME_EMAIL_ERROR',
    ];
}
