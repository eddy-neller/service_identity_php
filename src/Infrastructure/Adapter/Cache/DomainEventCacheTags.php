<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Cache;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\Event\UserDomainEventInterface;

/**
 * Source unique des tags de cache à purger pour un fait métier donné.
 *
 * Extraite du `CacheInvalidationMiddleware`, qui l'utilise seul : celui-ci orchestre (quand
 * purger), cette classe décide (quoi purger). C'est ici qu'un contexte supplémentaire —
 * Shop, dont les queries sont cachées sans être invalidées — viendrait se brancher.
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
