<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event;

use App\Application\Shared\Port\DomainEventBusInterface;
use App\Domain\SharedKernel\Event\DomainEventInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Écrit les Domain Events dans l'outbox Doctrine (transport `domain_events`).
 *
 * Le dispatch s'arrête au SendMessageMiddleware : aucun handler ne s'exécute ici,
 * d'où l'absence de lecture de `HandledStamp`. L'INSERT emprunte la connexion
 * Doctrine courante, donc la transaction ouverte par l'appelant.
 *
 * Chaque événement est aussi remis au `PublishedDomainEventCollector`, qui permet au
 * `CacheInvalidationMiddleware` de purger les tags de cache sans attendre le worker.
 */
final readonly class MessengerDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $bus,
        private PublishedDomainEventCollector $collector,
    ) {
    }

    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    private function publish(DomainEventInterface $event): void
    {
        $this->bus->dispatch($event);
        $this->collector->record($event);
    }
}
