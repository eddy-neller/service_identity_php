<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Auth\Logout;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\RefreshTokenHasherInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;

final readonly class LogoutCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private RefreshTokenHasherInterface $refreshTokenHasher,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(LogoutCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $hash = RefreshTokenHash::fromString($this->refreshTokenHasher->hash($command->refreshToken));

        $this->transactional->transactional(function () use ($userId, $hash): void {
            $storedToken = $this->refreshTokenRepository->findByHash($hash);

            if (null !== $storedToken && $storedToken->belongsTo($userId)) {
                $this->refreshTokenRepository->delete($storedToken);
            }
        });
    }
}
