<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop;

use App\Infrastructure\Entity\Shop\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function countNbProductByCategory(string $categoryId): mixed
    {
        $qb = $this->createQueryBuilder('p');

        $query = $qb
            ->select(
                $qb->expr()->count('p.id')
            )
            ->where(
                $qb->expr()->eq('p.category', ':categoryId')
            )
            ->setParameter('categoryId', $categoryId)
        ;

        return $query
            ->getQuery()
            ->getSingleScalarResult();
    }
}
