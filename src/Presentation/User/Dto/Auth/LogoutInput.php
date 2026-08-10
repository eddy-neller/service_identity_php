<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Auth;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class LogoutInput
{
    #[Groups(['auth:write'])]
    #[Assert\NotBlank]
    public string $refreshToken;
}
