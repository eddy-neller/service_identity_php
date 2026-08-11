<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event;

use App\Application\Shared\Port\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Inbox portée par la table `processed_domain_event`, sur la connexion Doctrine courante :
 * un `markProcessed()` appelé depuis un callback transactionnel est donc commité — ou annulé —
 * avec l'effet du handler.
 */
final readonly class DoctrineDomainEventLedger implements DomainEventLedgerInterface
{
    private const string TABLE = 'processed_domain_event';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function hasProcessed(string $eventId, string $handler): bool
    {
        $found = $this->connection()->fetchOne(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE event_id = :eventId AND handler = :handler',
            ['eventId' => $eventId, 'handler' => $handler],
        );

        return false !== $found;
    }

    public function markProcessed(string $eventId, string $handler): void
    {
        // Deux workers peuvent traiter la même redélivrance en parallèle : la clé primaire
        // composite arbitre, le perdant n'a rien à faire.
        $this->connection()->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (event_id, handler, processed_at)'
            . ' VALUES (:eventId, :handler, :processedAt)'
            . ' ON CONFLICT (event_id, handler) DO NOTHING',
            [
                'eventId' => $eventId,
                'handler' => $handler,
                'processedAt' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }
}
