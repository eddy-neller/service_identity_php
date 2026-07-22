<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;

interface UserUniquenessCheckerInterface
{
    public function ensureEmailAndUsernameAvailable(EmailAddress $email, Username $username): void;

    public function ensureEmailAvailable(EmailAddress $email, ?UserId $excludeUserId = null): void;

    public function ensureUsernameAvailable(Username $username, ?UserId $excludeUserId = null): void;
}
