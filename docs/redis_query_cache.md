# Cache Redis des Queries

Ce document décrit le cache applicatif (Redis, tag-aware) posé sur certaines Queries CQRS. Il est
distinct du cache HTTP Varnish/API Platform décrit dans [`varnish_cache.md`](varnish_cache.md) : ici
on cache le **résultat du handler**, avant toute sérialisation HTTP.

## 1) Vue d'ensemble

```text
query.bus : logging → exception unwrapping → QueryCacheMiddleware → Messenger defaults → handler
```

`QueryCacheMiddleware` (`src/Infrastructure/Messenger/CQRS/Middleware/QueryCacheMiddleware.php`)
intercepte chaque Query sur `query.bus`. Si la Query n'implémente pas `CacheableQueryInterface`, le
middleware est un no-op et délègue directement à la suite de la chaîne. Voir
[`CQRS_messenger.md`](CQRS_messenger.md) pour la place de ce middleware dans le pipeline complet.

## 2) Contrats

- **Application** — `App\Application\Shared\CQRS\Query\CacheableQueryInterface`
  (`src/Application/Shared/CQRS/Query/CacheableQueryInterface.php`) : étend `QueryInterface`, ajoute
  `cacheKey(): string`, `cacheTtl(): int`, `cacheTags(): array`. Une Query cacheable porte donc
  elle-même la logique de clé/TTL/tags — aucune configuration externe. C'est le **seul** contrat de
  cache connu de l'Application.
- **Infrastructure** — `App\Infrastructure\Service\Cache\QueryCacheInterface`
  (`src/Infrastructure/Service/Cache/QueryCacheInterface.php`) : contrat interne
  `get(key, ttlSeconds, tags, callback)` / `invalidateTags(tags)`. Ce n'est **pas** un Port : aucun
  use case ne l'injecte, seuls le middleware et les listeners Infrastructure le consomment
  (cf. `src/Infrastructure/AGENTS.md`).

## 3) Implémentation (Infrastructure)

- `App\Infrastructure\Service\Cache\SymfonyTagAwareQueryCache`
  (`src/Infrastructure/Service/Cache/SymfonyTagAwareQueryCache.php`) implémente `QueryCacheInterface`
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
Redis tag-aware. Seul le domaine **User** a un listener d'invalidation aujourd'hui :

`App\Infrastructure\EventListener\UserCacheInvalidationListener`
(`src/Infrastructure/EventListener/UserCacheInvalidationListener.php`) écoute les événements
`user.*` (création, mise à jour, suppression, activation, reset de mot de passe, avatar, tentatives de
mot de passe erronées...) et invalide systématiquement `['users-collection', 'user-' . $userId]` — ce
qui purge à la fois la liste utilisateurs et l'item de l'utilisateur concerné.

C'est aujourd'hui le seul domaine caché, donc le seul à invalider : le trou qui existait ici — des
listes catalogue cachées 1h que **rien** ne purgeait — est parti avec le contexte `Shop`. Il y a été
refermé, `service_shop` invalidant par tags depuis ses Domain Events.

La règle à retenir pour la suite : **une Query ne devient `CacheableQueryInterface` qu'avec son
invalidation.** Les deux ensemble, ou aucun des deux.

## 7) Diagnostic

```bash
php bin/console debug:messenger query.bus   # vérifie que QueryCacheMiddleware est bien sur query.bus
php bin/console cache:pool:list              # liste les pools, dont cache.tag
php bin/console cache:pool:clear cache.tag   # purge tout le cache de queries
```

En tests (`when@test`), `cache.tag` utilise `cache.adapter.array` : pas de dépendance Redis, cache
non partagé entre process, réinitialisé à chaque exécution.
