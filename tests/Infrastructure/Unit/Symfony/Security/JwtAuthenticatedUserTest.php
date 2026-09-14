<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Security;

use App\Infrastructure\Symfony\Security\JwtAuthenticatedUser;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JwtAuthenticatedUserTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testCreateFromPayloadBuildsAnIdentifiedUser(): void
    {
        $user = JwtAuthenticatedUser::createFromPayload(self::USER_ID, ['roles' => ['ROLE_USER', 'ROLE_ADMIN']]);

        $this->assertInstanceOf(JwtAuthenticatedUser::class, $user);
        $this->assertSame(self::USER_ID, $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        $this->assertSame(self::USER_ID, $user->getId()->toString());
    }

    public function testCreateFromPayloadDefaultsToNoRoles(): void
    {
        $user = JwtAuthenticatedUser::createFromPayload(self::USER_ID, []);

        $this->assertSame([], $user->getRoles());
    }

    /**
     * `InvalidTokenException` est une `AuthenticationException` : c'est ce qui fait répondre 401 au
     * firewall. Toute autre exception sortirait de `JWTAuthenticator` et deviendrait un 500.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('malformedClaims')]
    public function testCreateFromPayloadRejectsMalformedClaims(mixed $subject, array $payload): void
    {
        $this->expectException(InvalidTokenException::class);

        JwtAuthenticatedUser::createFromPayload($subject, $payload);
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function malformedClaims(): iterable
    {
        yield 'subject is not a UUID' => ['not-an-uuid', ['roles' => ['ROLE_USER']]];
        yield 'subject is not a string' => [42, ['roles' => ['ROLE_USER']]];
        yield 'roles is not a list' => [self::USER_ID, ['roles' => 'ROLE_ADMIN']];
        yield 'a role is not a string' => [self::USER_ID, ['roles' => ['ROLE_USER', 12]]];
    }
}
