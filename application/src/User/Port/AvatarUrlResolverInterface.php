<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\ValueObject\Avatar;

interface AvatarUrlResolverInterface
{
    public function resolve(Avatar $avatar): ?string;
}
