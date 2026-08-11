<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event;

use App\Domain\SharedKernel\Event\DomainEventInterface;

/**
 * Mémorise les Domain Events publiés pendant le traitement d'une commande.
 *
 * L'outbox écrit les événements dans la même transaction que l'agrégat : leurs réactions
 * ne s'exécutent donc qu'une fois le worker réveillé, quelques dizaines de millisecondes
 * après la réponse HTTP. C'est le comportement voulu pour les effets externes (e-mails,
 * provisioning), mais pas pour l'invalidation du cache de lecture : le client qui relit
 * juste après son écriture obtiendrait une réponse périmée.
 *
 * Ce collecteur donne au `CacheInvalidationMiddleware` la liste des faits métier survenus,
 * pour qu'il purge les tags **après le commit** et **avant la réponse**, sans rien retirer
 * à l'outbox.
 */
final class PublishedDomainEventCollector
{
    /** @var list<DomainEventInterface> */
    private array $events = [];

    public function record(DomainEventInterface $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Vide le collecteur et retourne son contenu.
     *
     * Toujours appelé, y compris quand la commande échoue : un worker traite des messages
     * en série, aucun événement ne doit fuir d'un message vers le suivant.
     *
     * @return list<DomainEventInterface>
     */
    public function release(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
