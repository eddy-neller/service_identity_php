<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Shared\Port\TransactionalInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransactional implements TransactionalInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function transactional(callable $operation)
    {
        return $this->entityManager->wrapInTransaction($operation);
    }
}
