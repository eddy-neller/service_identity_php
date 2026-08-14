<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger\Event\Handler\User;

use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Infrastructure\Http\ShopService\ShopCustomerClientInterface;
use App\Infrastructure\Symfony\Messenger\Event\DomainEventLedgerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Désactive le client `Shop` lorsqu'un compte utilisateur est supprimé.
 *
 * Même bascule que `ProvisionCustomerHandler` : l'effet est externe depuis le jalon 3, donc
 * `hasProcessed()` en garde d'entrée puis `markProcessed()` après succès, hors transaction.
 *
 * La traduction `userAccountId` → `customerId` a disparu avec le dépôt local. C'est désormais le
 * service distant qui la fait, et lui seul qui sait si un client est rattaché à ce compte : d'où
 * un port silencieux quand il n'y en a pas, là où ce handler testait `null` lui-même.
 */
#[AsMessageHandler(bus: 'event.bus', sign: true)]
final readonly class DisableCustomerHandler
{
    public function __construct(
        private ShopCustomerClientInterface $shopCustomerClient,
        private DomainEventLedgerInterface $ledger,
    ) {
    }

    public function __invoke(UserDeletedByAdminEvent $event): void
    {
        $eventId = $event->eventId();

        if ($this->ledger->hasProcessed($eventId, self::class)) {
            return;
        }

        $this->shopCustomerClient->disableCustomer($event->getUserId()->toString());

        $this->ledger->markProcessed($eventId, self::class);
    }
}
