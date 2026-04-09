<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetConfirmInput
{
    #[Assert\NotBlank]
    #[Groups(groups: ['user:item:users-password-reset-confirm'])]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^(?=.*[()!@#$%^&*_-])(?=.*\d)(?=.*[A-Z]).{8,30}$/',
        message: 'Invalid password.'
    )]
    #[Groups(groups: ['user:item:users-password-reset-confirm'])]
    public string $newPassword;

    #[Assert\NotBlank]
    #[Assert\EqualTo(
        propertyPath: 'newPassword',
        message: 'The password confirmation does not match.'
    )]
    #[Groups(groups: ['user:item:users-password-reset-confirm'])]
    public string $confirmNewPassword;
}
