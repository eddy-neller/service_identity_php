<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetCheckInput
{
    #[Groups(groups: ['user:item:users-password-reset-check'])]
    #[Assert\NotBlank]
    public string $token;
}
