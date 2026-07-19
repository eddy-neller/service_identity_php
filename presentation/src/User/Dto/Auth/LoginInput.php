<?php

declare(strict_types=1);

namespace App\Presentation\User\Dto\Auth;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class LoginInput
{
    #[Groups(['auth:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Groups(['auth:write'])]
    #[Assert\NotBlank]
    public string $password;
}
