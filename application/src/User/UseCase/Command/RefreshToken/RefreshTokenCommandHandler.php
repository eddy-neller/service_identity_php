<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\RefreshToken;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\RefreshTokenHasherInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\AuthTokens;
use App\Application\User\Service\AuthTokenIssuer;
use App\Domain\User\Exception\Security\InvalidRefreshTokenException;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;

final readonly class RefreshTokenCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private RefreshTokenHasherInterface $refreshTokenHasher,
        private AuthTokenIssuer $tokenIssuer,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(RefreshTokenCommand $command): AuthTokens
    {
        $hash = RefreshTokenHash::fromString($this->refreshTokenHasher->hash($command->refreshToken));
        $now = $this->clock->now();

        return $this->transactional->transactional(function () use ($hash, $now): AuthTokens {
            $storedToken = $this->refreshTokenRepository->findByHash($hash);

            if (null === $storedToken) {
                throw new InvalidRefreshTokenException();
            }

            if ($storedToken->isExpired($now)) {
                $this->refreshTokenRepository->delete($storedToken);

                throw new InvalidRefreshTokenException();
            }

            $user = $this->userRepository->findById($storedToken->getUserId());

            if (null === $user || $user->isLocked() || !$user->isActive()) {
                $this->refreshTokenRepository->delete($storedToken);

                throw new InvalidRefreshTokenException();
            }

            $this->refreshTokenRepository->delete($storedToken);

            return $this->tokenIssuer->issue($user, $now);
        });
    }
}
