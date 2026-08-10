<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\User;

use App\Domain\User\Model\User;

interface UserNotifierInterface
{
    public function sendActivationEmail(User $user, string $encodedToken): void;

    public function sendResetPasswordEmail(User $user, string $encodedToken): void;
}
