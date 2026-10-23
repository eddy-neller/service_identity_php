<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\Event\UserDomainEventInterface;
use App\Infrastructure\Messaging\IntegrationBusUnavailableException;
use App\Infrastructure\Messaging\UserIntegrationEvent;
use App\Infrastructure\Messaging\UserIntegrationEventPublisherInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Annonce aux autres services les faits publics de ce service.
 *
 * Il remplace `ProvisionCustomerHandler` et `DisableCustomerHandler`, qui appelaient un port dont
 * l'unique adaptateur journalisait. La différence de fond n'est pas le transport : c'est que **ce
 * service ne sait plus ce que l'autre va faire**. Il annonçait « provisionne un client » ; il
 * annonce désormais « ce compte a été créé ». Qui réagit, et comment, ne le concerne pas.
 *
 * ## Traduire, et rien d'autre
 *
 * Le `match` ci-dessous est la seule chose que ce handler décide : quel type du contrat correspond à
 * quel Domain Event. Il vit ici, et pas dans `UserIntegrationEvent`, parce que c'est ici que les
 * trois événements sont connus — le DTO du contrat, lui, n'a aucune raison de connaître le Domain.
 *
 * ## Un effet externe, donc hors transaction
 *
 * Publier est un appel réseau : il ne peut pas rejoindre la transaction du cas d'usage, et ne doit
 * surtout pas être placé dans un `transactional()` — ce serait tenir une transaction PostgreSQL,
 * donc ses verrous, pendant un aller-retour HTTP.
 *
 * C'est le second usage décrit par {@see DomainEventLedgerInterface}, celui de
 * `SendActivationEmailHandler` : `hasProcessed()` en garde d'entrée, publication, puis
 * `markProcessed()` **après** succès. La garantie est l'au-moins-une-fois dédupliqué, et c'est
 * exactement pour cela que les consommateurs dédupliquent sur l'`eventId` qu'on leur transmet.
 *
 * **L'ordre ne doit pas être inversé.** Publier puis marquer risque un doublon, que le consommateur
 * absorbe. Marquer puis publier risque une **perte définitive**, que personne ne rattrape.
 *
 * Si la publication échoue, l'exception remonte, le marquage n'a pas lieu, et Messenger rejoue le
 * message depuis l'outbox.
 *
 * ## Une panne du bus se rattrape seule, un défaut reste visible
 *
 * **Bus injoignable** ({@see IntegrationBusUnavailableException}) : l'échec est rendu à Messenger
 * comme `RecoverableMessageHandlingException`, qu'il rejoue **sans limite de nombre**, au rythme du
 * transport — 10 s, doublé à chaque essai, plafonné à 5 min. Le fait part dès le retour du bus, quelle
 * qu'ait été la durée de la panne. Avant, il partait au bout de six essais (une dizaine de minutes)
 * dans `failed_domain_events`, d'où seul un `messenger:failed:retry` manuel le sortait.
 *
 * **Tout autre échec** remonte tel quel : six essais, puis la file d'échec. Une requête que SNS
 * refuse ne passera jamais en attendant, et doit être vue.
 *
 * Rejouer sans limite ne rejoue que ce qui a échoué : Messenger saute les handlers qui ont déjà
 * réussi sur ce message (leur `HandledStamp` voyage avec lui), et le ledger les arrêterait de toute
 * façon. L'e-mail d'activation ne repart donc pas toutes les 5 minutes pendant la panne.
 *
 * `sign: true` comme tous les handlers de ce service : l'événement vient de **notre** outbox, donc
 * la signature HMAC de `kernel.secret` a un sens ici. C'est l'inverse chez le consommateur, dont
 * les messages viennent d'un service qui n'a pas notre secret.
 */
final readonly class PublishUserIntegrationEventHandler
{
    public function __construct(
        private UserIntegrationEventPublisherInterface $publisher,
        private DomainEventLedgerInterface $ledger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->publish($event, UserIntegrationEvent::TYPE_REGISTERED);
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserCreatedByAdmin(UserCreatedByAdminEvent $event): void
    {
        $this->publish($event, UserIntegrationEvent::TYPE_CREATED_BY_ADMIN);
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserDeletedByAdmin(UserDeletedByAdminEvent $event): void
    {
        $this->publish($event, UserIntegrationEvent::TYPE_DELETED);
    }

    private function publish(UserDomainEventInterface $event, string $type): void
    {
        if ($this->ledger->hasProcessed($event->eventId(), self::class)) {
            return;
        }

        try {
            $this->publisher->publish(new UserIntegrationEvent(
                eventId: $event->eventId(),
                type: $type,
                userAccountId: $event->getUserId()->toString(),
                occurredAt: $event->occurredOn(),
            ));
        } catch (IntegrationBusUnavailableException $e) {
            throw new RecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $this->ledger->markProcessed($event->eventId(), self::class);
    }
}
