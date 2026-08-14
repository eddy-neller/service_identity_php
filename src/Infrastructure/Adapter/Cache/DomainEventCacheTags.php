<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Cache;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\Event\UserDomainEventInterface;

/**
 * Source unique des tags de cache à purger pour un fait métier donné.
 *
 * Extraite du `CacheInvalidationMiddleware`, qui l'utilise seul : celui-ci orchestre (quand
 * purger), cette classe décide (quoi purger). C'est ici qu'un contexte supplémentaire viendrait
 * se brancher — et il devrait le faire **en même temps** qu'il rend une query cachable, jamais
 * après.
 *
 * Le typage porte sur le marqueur de contexte (`UserDomainEventInterface`), jamais sur les
 * événements un à un : sans lui, l'oubli d'un événement ne se verrait qu'à la lecture périmée.
 */
final readonly class DomainEventCacheTags
{
    /**
     * @return list<string> vide si l'événement n'affecte aucune query cachée
     */
    public function forEvent(DomainEventInterface $event): array
    {
        return match (true) {
            $event instanceof UserDomainEventInterface => [
                'users-collection',
                'user-' . $event->getUserId()->toString(),
            ],
            default => [],
        };
    }
}
