<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\User;

use App\Application\User\Port\AvatarUrlResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class AvatarUrlResolver implements AvatarUrlResolverInterface
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
    ) {
    }

    public function resolve(?string $avatarName): ?string
    {
        if (null === $avatarName || '' === $avatarName) {
            return null;
        }

        return rtrim((string) $this->parameterBag->get('app.avatar.uploadUrl'), '/') . '/' . ltrim($avatarName, '/');
    }
}
