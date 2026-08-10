<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Security;

enum ReauthenticationReason: string
{
    case PASSWORD_CHANGED = 'password_changed';
    case PASSWORD_RESET = 'password_reset';
    case ROLES_CHANGED = 'roles_changed';
    case ACCESS_DISABLED = 'access_disabled';
    case ACCOUNT_LOCKED = 'account_locked';
    case ACCOUNT_DELETED = 'account_deleted';
}
