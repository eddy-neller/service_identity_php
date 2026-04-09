<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetRequestInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(groups: ['user:item:users-password-reset-request'])]
    public string $email;
}
