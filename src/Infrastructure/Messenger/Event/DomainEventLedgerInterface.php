<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Event;

/**
 * Inbox des Domain Events : mémorise les couples (événement, handler) déjà traités.
 *
 * Deux usages, selon la nature de l'effet du handler :
 * - effet en base : appeler `markProcessed()` dans la même transaction que l'effet,
 *   ce qui donne un exactement-une-fois réel ;
 * - effet externe (e-mail, Redis) : `hasProcessed()` en garde d'entrée puis
 *   `markProcessed()` après succès — au-moins-une-fois, dédupliqué sur le chemin nominal.
 */
interface DomainEventLedgerInterface
{
    public function hasProcessed(string $eventId, string $handler): bool;

    public function markProcessed(string $eventId, string $handler): void;
}
