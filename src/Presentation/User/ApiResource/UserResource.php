<?php

declare(strict_types=1);

namespace App\Presentation\User\ApiResource;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Shared read model exposed as the `output` of the User-related resources.
 */
final class UserResource
{
    #[Groups(['onboarding:read', 'me:read', 'user_management:read'])]
    public string $id;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public ?string $firstname = null;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public ?string $lastname = null;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public string $username;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public string $email;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public array $roles = [];

    #[Groups(['onboarding:read', 'me:read', 'user_management:read', 'user_management:admin'])]
    public int $status;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read'])]
    public ?string $avatarUrl = null;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read'])]
    public DateTimeImmutable $lastVisit;

    #[Groups(['onboarding:item:read', 'me:item:read', 'user_management:item:read'])]
    public int $nbLogin = 0;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read'])]
    public DateTimeImmutable $createdAt;

    #[Groups(['onboarding:read', 'me:read', 'user_management:read'])]
    public DateTimeImmutable $updatedAt;
}
