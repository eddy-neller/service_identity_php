<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Shared\Port\TransactionalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final readonly class DoctrineTransactional implements TransactionalInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function transactional(callable $operation)
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();

            $this->entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            // Après un rollback l'identity map référence des entités qui n'existent plus
            // en base : l'EntityManager doit être fermé, comme le fait wrapInTransaction().
            $this->entityManager->close();

            // wrapInTransaction() appelle rollBack() sans condition ; si la connexion est
            // tombée, ce rollback lève et masque l'exception d'origine. On ne le tente
            // donc que s'il reste réellement une transaction ouverte.
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
