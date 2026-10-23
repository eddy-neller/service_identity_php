<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

/**
 * Publie un fait public sur le bus inter-services.
 *
 * Contrat **interne à Infrastructure**, et non Port applicatif : aucun cas d'usage ne l'injecte,
 * seul un handler de Domain Event le consomme. C'est la règle de `src/Infrastructure/AGENTS.md`,
 * la même qui garde `UserNotifierInterface` et `DomainEventLedgerInterface` ici.
 *
 * Publier est un **effet externe** : il ne peut pas rejoindre la transaction du handler, et doit
 * donc rester appelé hors de tout `transactional()` — un aller-retour réseau ne tient pas une
 * transaction PostgreSQL ouverte.
 *
 * **L'implémentation doit lever si la publication n'a pas abouti.** C'est ce qui permet au handler
 * de ne pas marquer l'événement comme traité, donc à Messenger de le rejouer. Une implémentation
 * silencieuse ferait perdre le fait sans trace.
 *
 * **Et elle doit dire si attendre a un sens.** Un bus injoignable lève
 * {@see IntegrationBusUnavailableException} : le handler rejouera alors la publication sans limite,
 * jusqu'au retour du bus. Tout autre échec lève autre chose, et finit dans la file d'échec après
 * quelques essais — une panne se rattrape seule, un défaut doit rester visible.
 */
interface UserIntegrationEventPublisherInterface
{
    /**
     * @throws IntegrationBusUnavailableException le bus est momentanément injoignable
     */
    public function publish(UserIntegrationEvent $event): void;
}
