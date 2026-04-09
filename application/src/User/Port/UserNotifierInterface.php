<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Model\User;

interface UserNotifierInterface
{
    public function sendActivationEmail(User $user, string $encodedToken): void;

    public function sendResetPasswordEmail(User $user, string $encodedToken): void;
}
