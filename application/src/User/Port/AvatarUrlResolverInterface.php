<?php

declare(strict_types=1);

namespace App\Application\User\Port;

interface AvatarUrlResolverInterface
{
    public function resolve(?string $avatarName): ?string;
}
