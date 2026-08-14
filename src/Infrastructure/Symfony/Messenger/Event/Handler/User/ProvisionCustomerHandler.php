<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Infrastructure\Http\ShopService\ShopCustomerClientInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Provisionne le client `Shop` rattaché à un compte utilisateur nouvellement créé.
 *
 * **L'effet est externe depuis le jalon 3.** Le contexte `Shop` vit dans `service_shop` : le
 * marquage ne peut plus partager la transaction de l'effet, et la garantie tombe de
 * l'exactement-une-fois réel à l'au-moins-une-fois dédupliqué sur le chemin nominal. C'est le
 * second usage décrit par `DomainEventLedgerInterface`, celui de `SendActivationEmailHandler` :
 * `hasProcessed()` en garde d'entrée, puis `markProcessed()` après succès.
 *
 * Deux conséquences à ne pas perdre de vue :
 * - l'idempotence de l'effet incombe à l'implémentation du port — un crash entre l'appel et le
 *   marquage fait rejouer l'appel, qui doit rester inoffensif ;
 * - l'appel ne doit pas être replacé dans un `transactional()`, sous peine de tenir une
 *   transaction PostgreSQL ouverte pendant un aller-retour réseau.
 */
final readonly class ProvisionCustomerHandler
{
    public function __construct(
        private ShopCustomerClientInterface $shopCustomerClient,
        private DomainEventLedgerInterface $ledger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->provision($event->eventId(), $event->getUserId()->toString());
    }

    #[AsMessageHandler(bus: 'event.bus', sign: true)]
    public function onUserCreatedByAdmin(UserCreatedByAdminEvent $event): void
    {
        $this->provision($event->eventId(), $event->getUserId()->toString());
    }

    private function provision(string $eventId, string $userId): void
    {
        if ($this->ledger->hasProcessed($eventId, self::class)) {
            return;
        }

        $this->shopCustomerClient->provisionCustomer($userId);

        $this->ledger->markProcessed($eventId, self::class);
    }
}
