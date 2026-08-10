<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Onboarding;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserActivationRequestInput
{
    #[Groups(groups: ['onboarding:item:users-register-resend:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
