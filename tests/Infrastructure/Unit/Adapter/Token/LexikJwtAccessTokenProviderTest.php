<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Adapter\Token;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Adapter\Token\AuthVersionStoreInterface;
use App\Infrastructure\Adapter\Token\JwtAccessTokenUser;
use App\Infrastructure\Adapter\Token\LexikJwtAccessTokenProvider;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class LexikJwtAccessTokenProviderTest extends TestCase
{
    private JWTTokenManagerInterface&MockObject $jwtTokenManager;

    private ParameterBagInterface&MockObject $parameterBag;

    private AuthVersionStoreInterface&MockObject $authVersionStore;

    private LexikJwtAccessTokenProvider $provider;

    protected function setUp(): void
    {
        $this->jwtTokenManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->authVersionStore = $this->createMock(AuthVersionStoreInterface::class);
        $this->provider = new LexikJwtAccessTokenProvider(
            $this->jwtTokenManager,
            $this->parameterBag,
            $this->authVersionStore,
        );
    }

    public function testIssueCreatesATokenWithUuidSubjectRolesAndAuthVersion(): void
    {
        $user = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('member'),
            email: EmailAddress::fromString('member@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray([]),
            now: new DateTimeImmutable('2025-01-01 10:00:00'),
        );

        $this->authVersionStore->expects($this->once())
            ->method('getOrCreate')
            ->with('550e8400-e29b-41d4-a716-446655440000')
            ->willReturn('auth-version');
        $this->jwtTokenManager->expects($this->once())
            ->method('createFromPayload')
            ->with(
                $this->callback(static function (UserInterface $tokenUser): bool {
                    return $tokenUser instanceof JwtAccessTokenUser
                        && '550e8400-e29b-41d4-a716-446655440000' === $tokenUser->getUserIdentifier()
                        && ['ROLE_USER'] === $tokenUser->getRoles();
                }),
                ['auth_version' => 'auth-version'],
            )
            ->willReturn('encoded-jwt');
        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('jwt_ttl')
            ->willReturn(900);

        $token = $this->provider->issue($user);

        $this->assertSame([
            'token' => 'encoded-jwt',
            'expiresIn' => 900,
        ], $token);
    }
}
