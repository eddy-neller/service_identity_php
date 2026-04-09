<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Infrastructure\Entity\RefreshToken;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 *
 * @implements RefreshTokenRepositoryInterface<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findInvalid(?DateTimeInterface $datetime = null): iterable
    {
        $expirationLimit = $datetime instanceof DateTimeInterface ? $datetime : new DateTime();

        return $this->createQueryBuilder('refreshToken')
            ->where('refreshToken.valid < :limit')
            ->setParameter('limit', $expirationLimit)
            ->getQuery()
            ->getResult();
    }

    public function findInvalidBatch(?DateTimeInterface $datetime = null, ?int $batchSize = null, int $offset = 0): iterable
    {
        $expirationLimit = $datetime instanceof DateTimeInterface ? $datetime : new DateTime();

        return $this->createQueryBuilder('refreshToken')
            ->where('refreshToken.valid < :limit')
            ->setParameter('limit', $expirationLimit)
            ->setFirstResult($offset)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getResult();
    }
}
