<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Onboarding;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserActivationValidationInput
{
    #[Groups(groups: ['onboarding:item:users-register-validation:write'])]
    #[Assert\NotBlank]
    public string $token;
}
