<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Onboarding;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class UserPreferences
{
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2, exactMessage: 'The language must be exactly 2 characters long.')]
    #[Groups(groups: ['onboarding:item:write'])]
    public string $lang;
}
