<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Service\Token;

use App\Infrastructure\Security\JwtAuthenticatedUser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JwtAuthenticatedUserTest extends TestCase
{
    public function testCreateFromPayloadBuildsAnIdentifiedUser(): void
    {
        $user = JwtAuthenticatedUser::createFromPayload(
            '550e8400-e29b-41d4-a716-446655440000',
            ['roles' => ['ROLE_USER', 'ROLE_ADMIN']],
        );

        $this->assertInstanceOf(JwtAuthenticatedUser::class, $user);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $user->getId()->toString());
    }

    public function testCreateFromPayloadRejectsAnInvalidSubject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JwtAuthenticatedUser::createFromPayload('not-an-uuid', ['roles' => ['ROLE_USER']]);
    }

    public function testCreateFromPayloadRejectsInvalidRoles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JwtAuthenticatedUser::createFromPayload(
            '550e8400-e29b-41d4-a716-446655440000',
            ['roles' => ['ROLE_USER', 12]],
        );
    }
}
