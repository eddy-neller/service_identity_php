<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\User;

use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Domain\User\ValueObject\Avatar;

final class AvatarUrlResolver implements AvatarUrlResolverInterface
{
    private const string AVATAR_BASE_URL = '/uploads/images/user/avatar';

    public function resolve(Avatar $avatar): ?string
    {
        if (null === $avatar->fileName() || '' === $avatar->fileName()) {
            return null;
        }

        return rtrim(self::AVATAR_BASE_URL, '/') . '/' . ltrim($avatar->fileName(), '/');
    }
}
