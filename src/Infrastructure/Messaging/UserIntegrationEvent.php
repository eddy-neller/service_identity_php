<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Fait public que ce service annonce aux autres, dans la version 1 du contrat.
 *
 * **C'est ici que vit le contrat, et nulle part ailleurs dans ce dépôt.** Le corps publié est
 * exactement ce que `toContract()` rend :
 *
 * ```json
 * { "id": "<eventId>", "type": "identity.user.registered", "version": 1,
 *   "occurredAt": "2026-09-15T12:00:00+00:00", "data": { "userAccountId": "<uuid>" } }
 * ```
 *
 * Aucune donnée personnelle n'y circule : seul l'identifiant du compte. Les consommateurs n'ont pas
 * besoin de l'e-mail ni du nom, et un bus est un endroit durable.
 *
 * `eventId` est l'`eventId()` du Domain Event qui a déclenché la publication. Il ne doit **jamais**
 * être régénéré : c'est la clé de déduplication des consommateurs, et elle doit rester la même si
 * la publication est rejouée depuis la file d'échec.
 *
 * ## Comment le faire évoluer
 *
 * **Un ajout ne change pas la version.** Un champ optionnel de plus est rétrocompatible : les
 * consommateurs ne lisent que les clés qu'ils connaissent et ignorent le reste. Incrémenter
 * `VERSION` pour un ajout serait donc une rupture gratuite — `service_shop` acquitte sans agir tout
 * ce qui dépasse la version qu'il connaît, et le provisionnement s'arrêterait en silence.
 *
 * Réservé à `VERSION` : ce qui casse un lecteur existant — une clé renommée ou retirée, un type
 * changé, une sémantique différente à clé constante. Et dans ce cas, **les consommateurs se livrent
 * d'abord** : eux savent acquitter une version qu'ils ne connaissent pas, l'inverse n'est pas vrai.
 *
 * Ajouter un **type** ne touche à rien non plus : un consommateur qui ne le connaît pas le
 * journalise et l'acquitte. C'est ce qui permet de publier sans coordonner un déploiement.
 *
 * > **Ce que rien ne vérifie, et qu'il faut savoir.** `service_shop` décrit le même contrat dans les
 * > fixtures JSON de son `UserIntegrationEventSerializerTest`, mais **les deux rives ne sont
 * > comparées par aucun test** : les dépôts sont séparés, et modifier `toContract()` en mettant à
 * > jour le test de ce dépôt laisse les deux suites vertes. Seule la vérification de bout en bout
 * > (`docs/integration_events.md`) attrape la rupture. Un vrai test *consumer-driven* — le
 * > consommateur publie ses attentes, la CI du producteur les vérifie — reste à écrire, et le
 * > deviendra nécessaire au deuxième consommateur.
 */
final readonly class UserIntegrationEvent
{
    /** Ne jamais incrémenter pour un simple ajout de champ — voir « Comment le faire évoluer ». */
    public const int VERSION = 1;

    public const string TYPE_REGISTERED = 'identity.user.registered';

    public const string TYPE_CREATED_BY_ADMIN = 'identity.user.created_by_admin';

    public const string TYPE_DELETED = 'identity.user.deleted';

    public function __construct(
        public string $eventId,
        public string $type,
        public string $userAccountId,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * Le corps à publier, dans l'ordre du contrat documenté.
     *
     * La forme vit sur ce DTO et non dans l'adaptateur SNS : elle appartient au contrat, pas au
     * transport. Un second transport publierait le même corps sans le redéfinir.
     *
     * @return array<string, mixed>
     */
    public function toContract(): array
    {
        return [
            'id' => $this->eventId,
            'type' => $this->type,
            'version' => self::VERSION,
            'occurredAt' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'data' => ['userAccountId' => $this->userAccountId],
        ];
    }
}
