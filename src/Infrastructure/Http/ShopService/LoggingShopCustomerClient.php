<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\ShopService;

use Psr\Log\LoggerInterface;

/**
 * Bouchon : journalise l'effet au lieu de le produire.
 *
 * Le contexte `Shop` a été retiré de ce service avant que le protocole inter-services ne soit
 * choisi. Il n'existe donc, provisoirement, **aucun provisionnement automatique** : chaque ligne
 * `warning` émise ici désigne un client à créer à la main sur `service_shop`, via
 * `POST /api/shop/customers`.
 *
 * Ne pas la faire échouer pour rendre le trou plus visible : le handler ne marquerait pas
 * l'événement, Messenger rejouerait six fois, et la file `failed` se remplirait de messages
 * qu'aucun retry ne peut satisfaire. Le log est la trace, la file n'a pas à l'être.
 *
 * Cette classe disparaît quand un adaptateur parlera réellement au service.
 */
final readonly class LoggingShopCustomerClient implements ShopCustomerClientInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function provisionCustomer(string $userAccountId): void
    {
        $this->log('provision', $userAccountId);
    }

    public function disableCustomer(string $userAccountId): void
    {
        $this->log('disable', $userAccountId);
    }

    private function log(string $operation, string $userAccountId): void
    {
        $this->logger->warning('Shop customer provisioning is not wired to any transport yet.', [
            'operation' => $operation,
            'user_account_id' => $userAccountId,
        ]);
    }
}
