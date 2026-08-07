<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer;

use App\Application\Shared\CQRS\Query\CacheableQueryInterface;

/**
 * Cette query ne résout qu'une association immuable : `userAccountId` -> `customerId`.
 *
 * Le Customer est provisionné une seule fois pour un compte (`ProvisionCustomerHandler`)
 * et son identifiant ne change jamais ensuite — seul son `status` évolue, et il n'est pas
 * exposé par `CurrentCustomerItem`. Le résultat est donc cachable sans risque de péremption.
 *
 * Le cas « Customer pas encore provisionné » (réaction asynchrone) reste correct :
 * `CustomerNotFoundException` traverse le callback, et le cache ne persiste rien
 * quand celui-ci lève — la requête suivante retentera en base.
 *
 * ⚠️ Si `CurrentCustomerItem` venait à porter des données mutables (status, compteurs…),
 * ce cache devrait être invalidé sur les événements du contexte Shop — `DomainEventCacheTags`
 * ne connaît aujourd'hui que ceux du contexte User.
 */
final readonly class DisplayMyCustomerQuery implements CacheableQueryInterface
{
    private const int CACHE_TTL_SECONDS = 86400;

    public function __construct(
        public string $userAccountId,
    ) {
    }

    public function cacheKey(): string
    {
        return 'customer-of-user-' . $this->userAccountId;
    }

    public function cacheTtl(): int
    {
        return self::CACHE_TTL_SECONDS;
    }

    public function cacheTags(): array
    {
        return ['customer-of-user-' . $this->userAccountId];
    }
}
