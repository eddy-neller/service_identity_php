# Cache Redis des Queries

Ce document décrit le cache applicatif (Redis, tag-aware) posé sur certaines Queries CQRS : ici on
cache le **résultat du handler**, avant toute sérialisation HTTP. Le service n'embarque plus de cache
HTTP Varnish.

## 1) Vue d'ensemble

```text
query.bus : logging → exception unwrapping → QueryCacheMiddleware → Messenger defaults → handler
```

`QueryCacheMiddleware` (`src/Infrastructure/Symfony/Messenger`)
intercepte chaque Query sur `query.bus`. Si la Query n'implémente pas `CacheableQueryInterface`, le
middleware est un no-op et délègue directement à la suite de la chaîne. Voir
[`CQRS_messenger.md`](CQRS_messenger.md) pour la place de ce middleware dans le pipeline complet.

## 2) Contrats

- **Application** — `App\Application\Shared\CQRS\Query\CacheableQueryInterface`
  (`src/Application/Shared/CQRS/Query/CacheableQueryInterface.php`) : étend `QueryInterface`, ajoute
  `cacheKey(): string`, `cacheTtl(): int`, `cacheTags(): array`. Une Query cacheable porte donc
  elle-même la logique de clé/TTL/tags — aucune configuration externe. C'est le **seul** contrat de
  cache connu de l'Application.
- **Infrastructure** — `App\Infrastructure\Adapter\Cache\QueryCacheInterface`
  (`src/Infrastructure/Adapter/Cache/QueryCacheInterface.php`) : contrat interne
  `get(key, ttlSeconds, tags, callback)` / `invalidateTags(tags)`. Ce n'est **pas** un Port : aucun
  use case ne l'injecte, seuls le middleware et les listeners Infrastructure le consomment
  (cf. `src/Infrastructure/AGENTS.md`).

## 3) Implémentation (Infrastructure)

- `App\Infrastructure\Adapter\Cache\SymfonyTagAwareQueryCache`
  (`src/Infrastructure/Adapter/Cache/SymfonyTagAwareQueryCache.php`) implémente `QueryCacheInterface`
  au-dessus du pool Symfony `cache.tag`.
- `config/packages/cache.yaml` :
  - `framework.cache.app: cache.adapter.redis`, `default_redis_provider: '%env(REDIS_URL)%'`.
  - Pool `cache.tag` → adapter `cache.adapter.redis_tag_aware` (permet `invalidateTags()`).
  - En `when@test`, `cache.tag` bascule sur `cache.adapter.array` (pas de Redis en tests).
- Redis local : `REDIS_URL` / `REDIS_PORT` dans `.env.dist`, service exposé par
  `docker-compose.override.yaml`.

Le résultat mis en cache est l'objet retourné par le handler (ReadModel, ex. `ProductList`,
`CategoryList`, `UserList`), sérialisé tel quel par le pool Symfony Cache. Ce sont des DTOs
`readonly` simples (pas d'entités Doctrine ni de closures), donc sérialisables sans précaution
particulière.

## 4) Mécanique du cache hit / miss (`QueryCacheMiddleware`)

```php
$result = $this->cache->get(
    key: $query->cacheKey(),
    ttlSeconds: $query->cacheTtl(),
    tags: $query->cacheTags(),
    callback: function () use ($envelope, $stack, &$handledEnvelope): mixed {
        $handledEnvelope = $stack->next()->handle($envelope, $stack);
        return $this->resultExtractor->extract($handledEnvelope);
    },
);
```

- **Miss** : le callback exécute la suite du pipeline (`handle()` réel), extrait le résultat via
  `HandledResultExtractor`, et `TagAwareCacheInterface::get()` le stocke sous `cacheKey()` avec
  `cacheTtl()` et les tags `cacheTags()`.
- **Hit** : le callback n'est jamais appelé, le handler n'est pas exécuté. Le middleware reconstruit
  un `HandledStamp` synthétique (`handlerName: 'query_cache'`) autour du résultat déjà en cache, pour
  que l'adapter de bus (`MessengerQueryBus`) conserve exactement le même contrat qu'un appel réel
  (`HandledResultExtractor` exige un unique `HandledStamp`).
- Une exception levée par le handler pendant un miss traverse le middleware sans être mise en cache
  (rien n'est stocké si le callback échoue).

## 5) Queries concernées aujourd'hui

Seules 2 Queries implémentent `CacheableQueryInterface` — **ce n'est pas limité aux listes** :

| Query | Type | Clé | Tags | TTL |
|---|---|---|---|---|
| `DisplayListUserQuery` | liste | `user-list-<sha256(payload)>` | `users-collection` | 3600s |
| `DisplayUserQuery` | item (single) | `user-item-<userId>` | `users-collection`, `user-<userId>` | 3600s |

Toutes les autres Queries implémentent seulement `QueryInterface` : elles passent le middleware sans
jamais toucher Redis.

`DisplayListProductQuery` et `DisplayListCategoryQuery` figuraient ici jusqu'au retrait du contexte
`Shop` ; elles vivent désormais dans `service_shop`.

Pour la Query de liste, la clé est un hash SHA-256 du payload normalisé (`page`, `itemsPerPage`,
`filters` et `orderBy` triés par clé via `ksort()`) — deux appels équivalents mais avec des filtres/tri
dans un ordre différent produisent la même clé de cache.

## 6) Invalidation par tags

`QueryCacheInterface::invalidateTags()` → `TagAwareCacheInterface::invalidateTags()` sur le pool
Redis tag-aware. Seul le domaine **User** a des queries cachées aujourd'hui, donc seul lui invalide.

**Ce n'est pas un listener d'événements**, mais un middleware :
`App\Infrastructure\Symfony\Messenger\CQRS\Middleware\CacheInvalidationMiddleware`, placé sur
`command.bus`. Il purge les tags des événements enregistrés pendant la commande — collectés par
`PublishedDomainEventCollector`, traduits en tags par `DomainEventCacheTags` — **après le
`transactional()` du handler et avant la réponse HTTP**.

Les deux bornes comptent. Invalider avant le commit ferait recacher à un lecteur concurrent l'état
d'**avant** l'écriture. Confier l'invalidation au worker la ferait arriver quelques dizaines de
millisecondes après la réponse, et le client qui relit juste après son écriture ne verrait pas sa
propre modification.

`DomainEventCacheTags` rend `['users-collection', 'user-' . $userId]` pour tout événement `user.*`
(création, mise à jour, suppression, activation, reset de mot de passe, avatar, tentatives de mot de
passe erronées…), ce qui purge à la fois la liste utilisateurs et l'item concerné.

C'est aujourd'hui le seul domaine caché, donc le seul à invalider : le trou qui existait ici — des
listes catalogue cachées 1h que **rien** ne purgeait — est parti avec le contexte `Shop`. Il y a été
refermé, `service_shop` invalidant par tags depuis ses Domain Events.

La règle à retenir pour la suite : **une Query ne devient `CacheableQueryInterface` qu'avec son
invalidation.** Les deux ensemble, ou aucun des deux.

### À traiter plus tard : Redis injoignable pendant la purge

Décidé le 2026-10-24, pas encore fait. La purge appelle Redis **après** le commit, dans la requête
d'écriture, sans tolérance déclarée. Ce qui se passe si Redis ne répond pas n'a pas été vérifié :
on peut craindre une réponse 500 pour une écriture pourtant committée — un client qui la rejoue
la ferait deux fois — et un cache périmé jusqu'à son TTL.

Le chantier : mesurer d'abord (Redis arrêté, une commande qui invalide), puis **déclarer** la
tolérance au lieu de l'espérer — journaliser et répondre normalement, avec un TTL qui borne la
péremption, ou une autre issue si la mesure le justifie. Même sujet côté `service_shop`, où la purge
touche aussi Varnish.

## 7) Diagnostic

```bash
php bin/console debug:messenger query.bus   # vérifie que QueryCacheMiddleware est bien sur query.bus
php bin/console cache:pool:list              # liste les pools, dont cache.tag
php bin/console cache:pool:clear cache.tag   # purge tout le cache de queries
```

En tests (`when@test`), `cache.tag` utilise `cache.adapter.array` : pas de dépendance Redis, cache
non partagé entre process, réinitialisé à chaque exécution.
