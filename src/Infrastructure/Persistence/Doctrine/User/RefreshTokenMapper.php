<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Model\RefreshToken as DomainRefreshToken;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\Security\RefreshTokenId;
use App\Infrastructure\Persistence\Doctrine\User\RefreshTokenEntity as DoctrineRefreshToken;
use Ramsey\Uuid\Uuid;

final class RefreshTokenMapper
{
    public function toDomain(DoctrineRefreshToken $entity): DomainRefreshToken
    {
        return DomainRefreshToken::reconstitute(
            id: RefreshTokenId::fromString($entity->getId()->toString()),
            userId: UserId::fromString($entity->getUserId()->toString()),
            hash: RefreshTokenHash::fromString($entity->getTokenHash()),
            expiresAt: $entity->getExpiresAt(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function toDoctrine(DomainRefreshToken $refreshToken): DoctrineRefreshToken
    {
        $entity = new DoctrineRefreshToken();
        $entity->setId(Uuid::fromString($refreshToken->getId()->toString()));
        $entity->setUserId(Uuid::fromString($refreshToken->getUserId()->toString()));
        $entity->setTokenHash($refreshToken->getHash()->toString());
        $entity->setExpiresAt($refreshToken->getExpiresAt());
        $entity->setCreatedAt($refreshToken->getCreatedAt());

        return $entity;
    }
}
