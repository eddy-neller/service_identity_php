# RefreshTokenCommandHandler

Fichier documenté : `application/src/User/UseCase/Command/RefreshToken/RefreshTokenCommandHandler.php`.

## Rôle

`RefreshTokenCommandHandler` renouvelle une session dont l'access token JWT a expiré.
Il est déclenché par `RefreshTokenCommand` via le `CommandBusInterface` (jamais appelé
directement).

Une route protégée telle que `/users/me` n'accepte que l'access token dans
`Authorization: Bearer <accessToken>`. Elle ne consomme pas le refresh token et ne le
renouvelle pas automatiquement. Le client appelle donc l'endpoint public
`POST /auth/token/refresh` avec son refresh token afin de recevoir une nouvelle paire de
tokens.

Le handler ne contient pas de logique métier : il vérifie l'état persistant du refresh
token et de l'utilisateur, puis délègue l'émission à `AuthTokenIssuer`. En cas de succès,
il retourne `AuthTokens`; en cas d'échec, il lève `InvalidRefreshTokenException`.

## Dépendances

Le handler dépend uniquement de Domaine + Ports Application :

- `RefreshTokenHasherInterface` — hash le token brut présenté par le client avant la
  recherche ; le token brut n'est jamais stocké ;
- `RefreshTokenRepositoryInterface` — charge et supprime le refresh token persistant ;
- `UserRepositoryInterface` — charge l'utilisateur auquel le token appartient ;
- `AuthTokenIssuer` — crée et persiste une nouvelle paire d'access/refresh tokens ;
- `ClockInterface` — fournit l'instant de référence, injecté et testable ;
- `TransactionalInterface` — rend atomiques la consommation de l'ancien token et
  l'émission du nouveau.

Il n'injecte aucun service Infrastructure concret ni framework.

## Mécanisme client / serveur

Par défaut, `JWT_TTL=900` : l'access token est valide 15 minutes. Le refresh token est
valide pendant `JWT_REFRESH_TTL=P30D` : 30 jours. Ces durées sont configurables, le
mécanisme reste le même.

```text
POST /auth/login
  -> accessToken A (15 min) + refreshToken R1 (30 jours)

GET /users/me avec Bearer A, après expiration
  -> 401 (JWTEXP0)

POST /auth/token/refresh avec R1
  -> accessToken B + refreshToken R2

GET /users/me avec Bearer B
  -> réponse attendue
```

Le client peut déclencher le renouvellement juste avant l'expiration connue de l'access
token, ou après un unique `401` d'expiration, puis rejouer une seule fois la requête qui
a échoué. Il doit remplacer **ensemble** les valeurs d'access token et de refresh token
par celles de la réponse. Si le renouvellement échoue, il efface la session locale et
redirige vers le login.

## Rotation et propriété à usage unique

Le refresh token est rotatif et à usage unique. Dans une unique transaction, le handler :

1. retrouve le token à partir de son hash ;
2. refuse un token absent ou expiré ;
3. charge son utilisateur et vérifie qu'il existe, est actif et non verrouillé ;
4. supprime l'ancien refresh token ;
5. émet une nouvelle paire via `AuthTokenIssuer`.

```text
RefreshTokenCommand(R1)
  -> hash(R1)
  -> transaction:
       findByHash(hash(R1))
       vérifier expiration et utilisateur
       delete(R1)
       issue(user) -> accessToken B + refreshToken R2
  -> AuthTokens(B, R2, Bearer, expiresIn)
```

Par conséquent, `R1` devient invalide dès que l'opération réussit : le client doit
conserver `R2`. Une tentative de réutilisation de `R1` est rejetée. Cette rotation réduit
la fenêtre d'utilisation d'un token dérobé et permet l'invalidation précise au logout.

Deux renouvellements concurrents avec le même token ne doivent pas tous deux réussir :
le premier le consomme ; le suivant doit être refusé une fois la suppression visible.

## Gestion des échecs et mapping HTTP

Les situations suivantes lèvent `InvalidRefreshTokenException`, mappée en HTTP 401 dans
`config/packages/api_platform.yaml` :

- token inconnu (hash absent) ;
- token expiré — il est supprimé avant le rejet ;
- utilisateur supprimé, verrouillé ou désactivé — le token est supprimé avant le rejet ;
- token déjà consommé par une précédente rotation.

Un refresh token vide est rejeté plus tôt par la validation du DTO
`RefreshTokenInput` de la Presentation.

## Frontières transactionnelles

Le hashage du token et la lecture de l'horloge sont préparés avant la transaction. La
recherche du token, les vérifications qui déterminent sa validité, sa suppression et
l'émission de la nouvelle paire sont effectuées dans la même transaction.

Cette frontière empêche qu'un refresh token soit supprimé sans qu'une nouvelle paire soit
émise, ou qu'il reste utilisable après qu'une nouvelle paire a été créée. Une exception
pendant l'émission entraîne le rollback de l'opération.

## Points d'attention

- Toujours passer par `CommandBusInterface`; ne jamais appeler `handle()` hors Application.
- Ne jamais envoyer le refresh token comme Bearer token vers les routes protégées.
- Ne jamais conserver l'ancien refresh token après un refresh réussi : il est déjà révoqué.
- Le token brut n'est jamais persisté, seul son hash l'est.
- L'horodatage passe par `ClockInterface`, jamais par `new DateTimeImmutable()`.
- Le logout invalide le refresh token courant ; l'access token reste valable jusqu'à sa
  propre expiration, qui doit donc rester courte.
