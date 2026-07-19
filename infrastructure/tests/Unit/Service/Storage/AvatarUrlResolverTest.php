<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Service\Storage;

use App\Infrastructure\Service\Storage\AvatarUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class AvatarUrlResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenAvatarNameIsMissing(): void
    {
        $resolver = new AvatarUrlResolver(new ParameterBag([
            'app.avatar.uploadUrl' => '/uploads/images/user/avatar',
        ]));

        $this->assertNull($resolver->resolve(null));
        $this->assertNull($resolver->resolve(''));
    }

    public function testResolveBuildsUrlFromConfiguredBaseUrl(): void
    {
        $resolver = new AvatarUrlResolver(new ParameterBag([
            'app.avatar.uploadUrl' => '/avatars/',
        ]));

        $this->assertSame('/avatars/avatar.jpg', $resolver->resolve('/avatar.jpg'));
    }
}
