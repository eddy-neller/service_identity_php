# RedisAuthVersionStore

Fichier documenté : `src/Infrastructure/Service/Token/RedisAuthVersionStore.php`.

## Rôle

`RedisAuthVersionStore` maintient une version d'authentification opaque par
utilisateur dans le pool Redis `cache.jwt_auth`.

Cette valeur est embarquée dans le claim JWT `auth_version` lors de l'émission
du token, puis comparée à Redis à chaque requête authentifiée. Elle permet de
révoquer simultanément tous les access tokens d'un utilisateur sans recharger
son entité Doctrine.

Le service est une implémentation Infrastructure de
`AuthVersionStoreInterface`. Il encapsule Symfony Cache et Redis : ni Domain,
ni Application, ni Presentation ne connaissent ce détail technique.

## Contrat

| Méthode | Comportement |
| --- | --- |
| `getOrCreate(userId)` | Retourne la version existante ou génère une nouvelle valeur aléatoire de 256 bits. Appelée à l'émission d'un access token. |
| `matches(userId, authVersion)` | Vérifie en temps constant que le claim JWT correspond à la valeur Redis. Une clé absente est refusée. |
| `rotate(userId)` | Remplace la valeur par une nouvelle valeur aléatoire et invalide immédiatement tous les JWT portant l'ancienne. |

Les clés sont préfixées par `auth-version-`. Le namespace du pool Symfony évite
les collisions avec les caches de requêtes, de rate limiting ou de rendu.

## Flux

```text
Émission
  RedisAuthVersionStore::getOrCreate(userId)
  -> claim JWT auth_version

Validation de requête
  JwtAuthVersionSubscriber::onJWTDecoded()
  -> RedisAuthVersionStore::matches(sub, auth_version)
  -> acceptation ou JWT_INVALID

Révocation critique
  UserReauthenticationRequiredEvent
  -> RedisAuthVersionStore::rotate(userId)
  -> tous les JWT précédents sont refusés
```

## Choix de conception

### Valeur aléatoire plutôt qu'un compteur

La version est une chaîne aléatoire de 256 bits, plutôt qu'un entier incrémental.
Si une clé Redis disparaît, `matches()` refuse les tokens existants. Lors de la
prochaine émission, `getOrCreate()` produit une valeur imprévisible : un ancien
token ne peut donc pas redevenir valide après une éviction de cache.

Un compteur initialisé à `1` aurait pu, au contraire, réactiver un ancien token
portant aussi la valeur `1` après la perte de la clé.

### Redis plutôt que Doctrine

L'objectif est de ne pas exécuter de requête SQL pour chaque Bearer token. Redis
introduit une lecture partagée légère par requête tout en permettant la
révocation immédiate de tous les appareils lors d'un événement critique.

Cela rend Redis nécessaire à l'authentification : une indisponibilité ou une
erreur de lecture doit faire échouer l'authentification, jamais accepter un JWT
sans vérification de version.

### Pas de TTL applicatif

La version n'a pas de TTL métier. Elle doit rester disponible tant que le compte
peut émettre ou présenter des tokens. Le pool `cache.jwt_auth` est donc une
donnée de sécurité à surveiller, même s'il utilise l'infrastructure Symfony
Cache.

## Environnement de test

Les tests API obtiennent un access token via `/login`, puis envoient une seconde
requête Bearer. API Platform peut redémarrer le kernel entre ces deux requêtes :
un cache en mémoire (`cache.adapter.array`) perdrait alors la valeur
`auth_version` créée pendant le login et le JWT serait légitimement refusé avec
une réponse 401.

Le pool `cache.jwt_auth` utilise donc `cache.adapter.filesystem` sous `when@test`
dans `config/packages/cache.yaml`. Il persiste entre les redémarrages de kernel
d'une même suite sans faire dépendre les tests de Redis. Ne pas le remplacer par
un cache en mémoire tant que les tests API génèrent un token puis l'utilisent sur
une requête distincte.

## Révocation

La rotation est appelée pour les motifs critiques de
`UserReauthenticationRequiredEvent` : mot de passe modifié ou réinitialisé,
compte désactivé, bloqué ou supprimé. Le subscriber supprime aussi les refresh
tokens concernés.

Pour `ROLES_CHANGED`, la version n'est pas tournée : le JWT existant conserve
ses rôles jusqu'à son expiration courte, et le prochain refresh émet un token
avec les rôles mis à jour. Ce compromis évite une révocation globale pour un
changement de rôle tout en bornant la période d'obsolescence à `JWT_TTL`.

## Limites

- Le service révoque tous les appareils d'un utilisateur ; il ne cible pas une
  session précise.
- Il ne remplace pas les validations métier contextuelles (propriété d'une
  ressource, abonnement, quota, etc.).
- Il ne remplace pas une blocklist `jti` si l'application doit révoquer
  uniquement le JWT courant sans invalider les autres appareils.

Voir aussi [`docs/JWT_authentication.md`](../../../../JWT_authentication.md)
pour l'architecture complète de l'authentification.
