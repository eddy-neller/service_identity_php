<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Token;

enum UserTokenScope: string
{
    case RegisterActivation = 'registerActivation';
    case ResetPassword = 'resetPassword';
}
