<?php

declare(strict_types=1);

namespace App\Application\User\Port;

use App\Domain\User\Model\RefreshToken;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\Security\RefreshTokenId;

interface RefreshTokenRepositoryInterface
{
    public function nextIdentity(): RefreshTokenId;

    public function save(RefreshToken $refreshToken): void;

    public function findByHash(RefreshTokenHash $hash): ?RefreshToken;

    public function delete(RefreshToken $refreshToken): void;

    public function deleteAllForUser(UserId $userId): void;
}
