<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\ShopService;

/**
 * Effets à produire sur `service_shop` quand un compte utilisateur naît ou disparaît.
 *
 * Le contexte `Shop` a quitté ce service au jalon 3 : provisionner un client n'est plus une
 * écriture locale, c'est un appel à un service distant. Le transport n'est pas encore arrêté —
 * HTTP, gRPC ou message — d'où ce port, qui laisse la question ouverte sans retenir le retrait.
 *
 * Les deux méthodes sont clées sur `userAccountId` : ce service n'a plus de dépôt capable de
 * traduire un identifiant de compte en identifiant de client, et n'a plus à savoir qu'un client
 * porte une identité propre.
 *
 * **Les deux opérations doivent être idempotentes côté implémentation.** Le ledger ne déduplique
 * que le chemin nominal ; une redélivrance survenue avant `markProcessed()` rappellera la même
 * méthode avec le même argument, et ne doit pas échouer pour autant.
 */
interface ShopCustomerClientInterface
{
    /**
     * Provisionne le client rattaché à ce compte. Ne lève pas s'il existe déjà.
     */
    public function provisionCustomer(string $userAccountId): void;

    /**
     * Désactive le client rattaché à ce compte. Silencieux si aucun client n'y est rattaché.
     */
    public function disableCustomer(string $userAccountId): void;
}
