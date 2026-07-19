<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\ValueObject\RefreshTokenId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

final readonly class RefreshToken
{
    private function __construct(
        private RefreshTokenId $id,
        private UserId $userId,
        private RefreshTokenHash $hash,
        private DateTimeImmutable $expiresAt,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public static function issue(
        RefreshTokenId $id,
        UserId $userId,
        RefreshTokenHash $hash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): self {
        if ($expiresAt <= $now) {
            throw new UserDomainException('A refresh token must expire in the future.');
        }

        return new self($id, $userId, $hash, $expiresAt, $now);
    }

    public static function reconstitute(
        RefreshTokenId $id,
        UserId $userId,
        RefreshTokenHash $hash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $userId, $hash, $expiresAt, $createdAt);
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function getId(): RefreshTokenId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getHash(): RefreshTokenHash
    {
        return $this->hash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
