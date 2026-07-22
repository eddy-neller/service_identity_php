<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\User\Port\RefreshTokenRepositoryInterface;
use App\Domain\User\Model\RefreshToken;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\RefreshTokenHash;
use App\Domain\User\ValueObject\Security\RefreshTokenId;
use App\Infrastructure\Entity\User\RefreshToken as DoctrineRefreshToken;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RefreshTokenMapper $mapper,
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function nextIdentity(): RefreshTokenId
    {
        return RefreshTokenId::fromString($this->uuidGenerator->generate());
    }

    public function save(RefreshToken $refreshToken): void
    {
        $entity = $this->em->find(DoctrineRefreshToken::class, $refreshToken->getId()->toString());
        $this->em->persist($this->mapper->toDoctrine($refreshToken, $entity));
        $this->em->flush();
    }

    public function findByHash(RefreshTokenHash $hash): ?RefreshToken
    {
        $entity = $this->em->createQueryBuilder()
            ->select('refreshToken')
            ->from(DoctrineRefreshToken::class, 'refreshToken')
            ->where('refreshToken.tokenHash = :hash')
            ->setParameter('hash', $hash->toString())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $entity instanceof DoctrineRefreshToken ? $this->mapper->toDomain($entity) : null;
    }

    public function delete(RefreshToken $refreshToken): void
    {
        $entity = $this->em->find(DoctrineRefreshToken::class, $refreshToken->getId()->toString());
        if (!$entity instanceof DoctrineRefreshToken) {
            return;
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    public function deleteAllForUser(UserId $userId): void
    {
        $this->em->createQueryBuilder()
            ->delete(DoctrineRefreshToken::class, 'refreshToken')
            ->where('refreshToken.userId = :userId')
            ->setParameter('userId', $userId->toString())
            ->getQuery()
            ->execute();
    }
}
