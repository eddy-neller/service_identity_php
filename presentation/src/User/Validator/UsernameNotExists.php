<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute] class UsernameNotExists extends Constraint
{
    public string $message = 'This username is already in use. Please choose another.';

    public const string SAME_USERNAME_ERROR = '7b639084-7451-4f4a-99e6-05e718ab61c4';

    protected const array ERROR_NAMES = [
        self::SAME_USERNAME_ERROR => 'SAME_USERNAME_ERROR',
    ];
}
