<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Account\AccountRecovery;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetCheckInput
{
    #[Groups(groups: ['account_recovery:item:users-password-reset-check'])]
    #[Assert\NotBlank]
    public string $token;
}
