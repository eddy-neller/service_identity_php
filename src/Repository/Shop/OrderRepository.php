<?php

declare(strict_types=1);

namespace App\Repository\Shop;

use App\Entity\Shop\Order;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity as User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findSuccessOrders(User $user): mixed
    {
        $qb = $this->createQueryBuilder('o');
        $qb->where($qb->expr()->eq('o.isPaid', 'true'))
            ->andWhere($qb->expr()->eq('o.user', ':user'))
            ->setParameter('user', $user)
            ->orderBy('o.id', Criteria::DESC)
        ;

        return $qb->getQuery()->getResult();
    }
}
