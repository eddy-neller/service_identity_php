<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Account\AccountRecovery;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetConfirmInput
{
    #[Assert\NotBlank]
    #[Groups(groups: ['account_recovery:item:users-password-reset-confirm'])]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid password.'
    )]
    #[Groups(groups: ['account_recovery:item:users-password-reset-confirm'])]
    public string $newPassword;
}
