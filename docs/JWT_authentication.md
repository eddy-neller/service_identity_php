# Authentification JWT

Cette API utilise des access tokens JWT courts et des refresh tokens persistants.
L'objectif est d'authentifier les requêtes courantes sans recharger l'utilisateur
Doctrine, tout en conservant une révocation immédiate pour les événements de
sécurité critiques.

## Contrat de l'access token

Les access tokens sont signés par Lexik JWT et expirent après `JWT_TTL` secondes
(`900`, soit 15 minutes, par défaut). Leur payload utile contient :

| Claim | Contenu | Usage |
| --- | --- | --- |
| `sub` | UUID de l'utilisateur | Identité stable du token |
| `roles` | Rôles globaux (`ROLE_USER`, `ROLE_ADMIN`, …) | Autorisation Symfony |
| `auth_version` | Secret opaque associé à l'utilisateur dans Redis | Révocation globale |
| `iat`, `exp` | Dates d'émission et d'expiration | Validité temporelle |

L'e-mail, le nom d'utilisateur, le profil et les droits contextuels ne font pas
partie du JWT. Un JWT est signé, mais son payload n'est pas chiffré : il ne doit
donc jamais contenir de données sensibles.

Les clients doivent considérer l'access token comme opaque. Les anciens tokens,
qui utilisaient l'e-mail comme identité et ne portaient pas `auth_version`, sont
intentionnellement refusés depuis cette évolution.

## Flux d'émission et d'authentification

```text
Login ou refresh
  -> AuthTokenIssuer
  -> LexikJwtAccessTokenProvider
  -> RedisAuthVersionStore::getOrCreate(userId)
  -> JWT signé (sub, roles, auth_version, exp)

Requête Bearer
  -> JWTAuthenticator Lexik : signature et expiration
  -> JwtAuthVersionSubscriber (JWT_DECODED) : version Redis
  -> JwtAuthenticatedUser construit depuis le payload
  -> Security / voters / Provider API Platform
```

Le firewall `main` utilise le provider `lexik_jwt`, non le provider Doctrine.
`JwtAuthenticatedUser` est donc une identité Symfony légère, indépendante de
l'entité Doctrine. Il expose toutefois `getId(): UuidInterface`, ce qui permet
aux endpoints `/me`, aux resolvers client et aux voters existants de travailler
avec la même identité utilisateur.

Si `auth_version` ne correspond pas à Redis, si elle est absente, ou si `sub`
n'est pas un UUID valide, `JwtAuthVersionSubscriber` marque le payload invalide.
Lexik déclenche alors l'événement `JWT_INVALID`, puis `JwtSubscriber` produit la
réponse HTTP 401 standardisée. Les deux subscribers ont donc des rôles distincts :
validation de session d'un côté, rendu des échecs JWT de l'autre.

## Refresh tokens et révocation

Les refresh tokens sont aléatoires, stockés sous forme de hash en base et
rotatifs : un refresh valide est supprimé puis remplacé par un nouveau. Leur TTL
est défini par `JWT_REFRESH_TTL` (`P30D` par défaut).

`UserReauthenticationRequiredEvent` pilote l'invalidation de session :

| Événement | Access tokens | Refresh tokens |
| --- | --- | --- |
| Mot de passe modifié ou réinitialisé | Révoqués immédiatement (rotation Redis) | Tous supprimés |
| Compte désactivé, bloqué ou supprimé | Révoqués immédiatement (rotation Redis) | Tous supprimés |
| Rôles modifiés | Valides jusqu'à `exp` (15 min max) | Conservés ; le prochain refresh émet les nouveaux rôles |
| Déconnexion | Valide jusqu'à `exp` | Le refresh token fourni est supprimé |

`RedisAuthVersionStore` conserve une valeur aléatoire de 256 bits par utilisateur.
Une valeur absente est refusée lors de la validation : après une perte de cache,
les access tokens existants ne sont pas réactivés. La prochaine connexion ou le
prochain refresh crée une nouvelle version.

Ce mécanisme révoque tous les appareils pour les cas critiques. Il ajoute une
lecture Redis par requête authentifiée, mais aucune requête Doctrine. La blocklist
Lexik basée sur `jti` n'est pas activée : elle ne permettrait pas, seule,
d'invalider tous les tokens connus d'un utilisateur sans mémoriser chaque `jti`.

## Autorisation et données métier

Les rôles JWT sont limités aux rôles globaux et relativement stables. Les règles
contextuelles restent calculées à partir des données métier actuelles : propriété
d'une adresse, droits sur une commande, abonnement, quota ou accès temporaire.

De même, `/me` utilise le JWT uniquement pour obtenir l'UUID ; son profil est lu
par le cas d'usage dédié et bénéficie du cache de requêtes existant. Il ne faut
pas enrichir le JWT avec le profil pour éviter cette lecture.

Les actions sensibles conservent leurs validations côté serveur : mot de passe,
refresh, gestion de compte, paiement et toute autorisation dépendante de l'état
métier. Un rôle porté par un JWT prouve l'état au moment de son émission, pas
l'état présent.

## Exploitation et déploiement

- Redis doit être disponible pour émettre et valider les access tokens.
- Le pool `cache.jwt_auth` n'a pas de TTL métier ; il doit être surveillé comme
  une donnée de sécurité, distincte des caches de rendu évictables.
- Une indisponibilité Redis rend l'authentification indisponible plutôt que
  d'accepter un token non vérifié.
- Toute rotation des clés JWT, modification de `JWT_TTL` ou changement de claims
  doit être traitée comme une évolution du contrat d'authentification.
- Le passage à `sub` est une coupure assumée : les clients reçoivent 401 avec un
  ancien access token et doivent effectuer un refresh ou une nouvelle connexion.

## Fichiers de référence

- `config/packages/security.yaml` : firewall et provider `lexik_jwt`.
- `config/packages/lexik_jwt_authentication.yaml` : clés, TTL et claim `sub`.
- `infrastructure/src/Service/Token/LexikJwtAccessTokenProvider.php` : émission.
- `infrastructure/src/EventSubscriber/JwtAuthVersionSubscriber.php` : validation Redis.
- `infrastructure/src/EventSubscriber/User/UserEventSubscriber.php` : révocation.
