<?php

declare(strict_types=1);

namespace App\Application\User\Service;

use App\Application\Shared\DateIntervalTrait;
use App\Application\Shared\Port\ConfigInterface;
use App\Application\User\Port\AccessTokenProviderInterface;
use App\Application\User\Port\RefreshTokenHasherInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Application\User\Port\TokenProviderInterface;
use App\Application\User\ReadModel\AuthTokens;
use App\Domain\User\Model\RefreshToken;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use DateTimeImmutable;

final readonly class AuthTokenIssuer
{
    use DateIntervalTrait;

    public function __construct(
        private AccessTokenProviderInterface $accessTokenProvider,
        private TokenProviderInterface $tokenProvider,
        private RefreshTokenHasherInterface $refreshTokenHasher,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private ConfigInterface $config,
    ) {
    }

    public function issue(User $user, DateTimeImmutable $now): AuthTokens
    {
        $accessToken = $this->accessTokenProvider->issue($user);

        $rawRefreshToken = $this->tokenProvider->generateRandomToken();

        $refreshToken = RefreshToken::issue(
            id: $this->refreshTokenRepository->nextIdentity(),
            userId: $user->getId(),
            hash: RefreshTokenHash::fromString(
                $this->refreshTokenHasher->hash($rawRefreshToken)
            ),
            expiresAt: $now->add(
                $this->createInterval(
                    $this->config->getString('jwt_refresh_ttl', 'P30D')
                )
            ),
            now: $now,
        );

        $this->refreshTokenRepository->save($refreshToken);

        return AuthTokens::of(
            $accessToken,
            $rawRefreshToken
        );
    }
}
