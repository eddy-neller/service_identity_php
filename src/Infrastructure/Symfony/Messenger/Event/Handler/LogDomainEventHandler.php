<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Journalise tout Domain Event consommé. Typé sur l'interface : aucun nouvel événement
 * n'a besoin d'être déclaré ici pour être tracé.
 *
 * Pas de ledger : réécrire une ligne de log lors d'une redélivrance est sans conséquence,
 * et `event_id` permet justement de repérer les rejeux.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class LogDomainEventHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DomainEventInterface $event): void
    {
        $this->logger->info('Domain event handled', [
            'event' => $event->eventName(),
            'event_id' => $event->eventId(),
            'aggregate_id' => $event->aggregateId(),
            'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
        ]);
    }
}
