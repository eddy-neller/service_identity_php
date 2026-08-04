# Cache Redis des Queries

Ce document décrit le cache applicatif (Redis, tag-aware) posé sur certaines Queries CQRS. Il est
distinct du cache HTTP Varnish/API Platform décrit dans [`varnish_cache.md`](varnish_cache.md) : ici
on cache le **résultat du handler**, avant toute sérialisation HTTP.

## 1) Vue d'ensemble

```text
query.bus : logging → exception unwrapping → QueryCacheMiddleware → Messenger defaults → handler
```

`QueryCacheMiddleware` (`infrastructure/src/Messenger/CQRS/Middleware/QueryCacheMiddleware.php`)
intercepte chaque Query sur `query.bus`. Si la Query n'implémente pas `CacheableQueryInterface`, le
middleware est un no-op et délègue directement à la suite de la chaîne. Voir
[`CQRS_messenger.md`](CQRS_messenger.md) pour la place de ce middleware dans le pipeline complet.

## 2) Contrats (Application)

- `App\Application\Shared\CQRS\Query\CacheableQueryInterface`
  (`application/src/Shared/CQRS/Query/CacheableQueryInterface.php`) : étend `QueryInterface`, ajoute
  `cacheKey(): string`, `cacheTtl(): int`, `cacheTags(): array`. Une Query cacheable porte donc
  elle-même la logique de clé/TTL/tags — aucune configuration externe.
- `App\Application\Shared\Port\QueryCacheInterface`
  (`application/src/Shared/Port/QueryCacheInterface.php`) : Port générique
  `get(key, ttlSeconds, tags, callback)` / `invalidateTags(tags)`. Application ne connaît que ce
  contrat, jamais Redis ni Symfony Cache directement (règle des couches, cf. `AGENTS.md`).

## 3) Implémentation (Infrastructure)

- `App\Infrastructure\Service\Cache\SymfonyTagAwareQueryCache`
  (`infrastructure/src/Service/Cache/SymfonyTagAwareQueryCache.php`) implémente `QueryCacheInterface`
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

Seules 4 Queries implémentent `CacheableQueryInterface` — **ce n'est pas limité aux listes** :

| Query | Type | Clé | Tags | TTL |
|---|---|---|---|---|
| `DisplayListProductQuery` | liste | `product:list:<sha256(payload)>` | `products-collection` | 3600s |
| `DisplayListCategoryQuery` | liste | `category:list:<sha256(payload)>` | `categories-collection` | 3600s |
| `DisplayListUserQuery` | liste | `user:list:<sha256(payload)>` | `users-collection` | 3600s |
| `DisplayUserQuery` | item (single) | `user:item:<userId>` | `users-collection`, `user-<userId>` | 3600s |

Toutes les autres Queries (`DisplayListAddressQuery`, `DisplayListCustomerQuery`, etc.) implémentent
seulement `QueryInterface` : elles passent le middleware sans jamais toucher Redis.

Pour les 3 Queries de liste, la clé est un hash SHA-256 du payload normalisé (`page`, `itemsPerPage`,
`filters` et `orderBy` triés par clé via `ksort()`) — deux appels équivalents mais avec des filtres/tri
dans un ordre différent produisent la même clé de cache.

## 6) Invalidation par tags

`QueryCacheInterface::invalidateTags()` → `TagAwareCacheInterface::invalidateTags()` sur le pool
Redis tag-aware. Seul le domaine **User** a un listener d'invalidation aujourd'hui :

`App\Infrastructure\EventListener\UserCacheInvalidationListener`
(`infrastructure/src/EventListener/UserCacheInvalidationListener.php`) écoute les événements
`user.*` (création, mise à jour, suppression, activation, reset de mot de passe, avatar, tentatives de
mot de passe erronées...) et invalide systématiquement `['users-collection', 'user-' . $userId]` — ce
qui purge à la fois la liste utilisateurs et l'item de l'utilisateur concerné.

**Point d'attention** : il n'existe **aucun listener équivalent pour `products-collection` et
`categories-collection`**. Une création/mise à jour/suppression de produit ou catégorie n'invalide pas
le cache de `DisplayListProductQuery` / `DisplayListCategoryQuery` — ces listes ne se rafraîchissent
qu'à l'expiration du TTL (1h). À traiter si des écritures catalogue doivent être visibles avant
l'expiration : ajouter un listener symétrique à `UserCacheInvalidationListener`, sur les événements
domain Product/Category, taguant `products-collection` / `categories-collection`.

## 7) Diagnostic

```bash
php bin/console debug:messenger query.bus   # vérifie que QueryCacheMiddleware est bien sur query.bus
php bin/console cache:pool:list              # liste les pools, dont cache.tag
php bin/console cache:pool:clear cache.tag   # purge tout le cache de queries
```

En tests (`when@test`), `cache.tag` utilise `cache.adapter.array` : pas de dépendance Redis, cache
non partagé entre process, réinitialisé à chaque exécution.
