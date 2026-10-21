<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Double\Messaging;

use App\Infrastructure\Messaging\UserIntegrationEvent;
use App\Infrastructure\Messaging\UserIntegrationEventPublisherInterface;

/**
 * Publie en mémoire. Enregistré sous `when@test` dans `config/services.yaml`.
 *
 * C'est ce qui garantit qu'**aucune suite ne dépend de la brique `aws_local`** : LocalStack exige un
 * jeton depuis mars 2026, et la CI n'en a pas. Le pendant côté `service_shop` est son transport
 * `USER_EVENTS_TRANSPORT_DSN=in-memory://`.
 *
 * Les événements publiés restent accessibles : une suite d'API peut ainsi vérifier qu'un parcours
 * a bien annoncé le fait attendu, sans réseau.
 */
final class InMemoryUserIntegrationEventPublisher implements UserIntegrationEventPublisherInterface
{
    /** @var list<UserIntegrationEvent> */
    private array $published = [];

    public function publish(UserIntegrationEvent $event): void
    {
        $this->published[] = $event;
    }

    /** @return list<UserIntegrationEvent> */
    public function published(): array
    {
        return $this->published;
    }
}
