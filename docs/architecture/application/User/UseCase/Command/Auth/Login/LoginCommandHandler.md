# LoginCommandHandler

Fichier documenté : `application/src/User/UseCase/Command/Login/LoginCommandHandler.php`.

## Rôle

`LoginCommandHandler` orchestre le cas d'usage d'authentification par email/mot de passe.
Il est déclenché par `LoginCommand` via le `CommandBusInterface` (jamais appelé directement).

Il ne contient pas de logique métier : les invariants (verrouillage, comptage des
tentatives, activation du compte, enregistrement d'une connexion) vivent dans l'agrégat
`Domain\User\Model\User`. Le handler se contente de :

- charger l'utilisateur ;
- déléguer les décisions métier à l'agrégat ;
- persister l'état via le repository ;
- émettre la paire de tokens via le service applicatif.

En cas de succès il retourne un read model `AuthTokens` ; en cas d'échec il laisse remonter
une exception Domaine mappée en statut HTTP par API Platform.

## Dépendances

Le handler dépend uniquement de Domaine + Ports Application :

- `UserRepositoryInterface` — charger (`findByEmail`) et persister (`save`) l'utilisateur ;
- `PasswordHasherInterface` — vérifier le mot de passe fourni contre le hash stocké ;
- `AuthTokenIssuer` — émettre l'access token et le refresh token (`issue`) ;
- `ClockInterface` — horodatage injecté (`now`), jamais `new DateTime` ;
- `ConfigInterface` — lire `app.security.max_login_attempts` ;
- `TransactionalInterface` — délimiter les frontières transactionnelles autour des écritures.

Il n'injecte aucun service Infrastructure concret ni framework.

## Flux nominal

```text
LoginCommand(email, password)
  -> findByEmail(email)              (lecture)
  -> assertions metier (non verrouille, mot de passe valide, compte actif)
  -> transaction:
       resetWrongPasswordAttempts(now)
       recordSuccessfulLogin(now)
       save(user)
       AuthTokenIssuer::issue(user, now) -> AuthTokens
  -> AuthTokens(accessToken, refreshToken, tokenType, expiresIn)
```

## Gestion des échecs et verrouillage

À chaque mot de passe erroné, l'agrégat incrémente son compteur
(`registerWrongPasswordAttempt`) et se verrouille lui-même dès que le seuil
`app.security.max_login_attempts` est atteint (`totalWrongPassword >= maxAttempts`).

Séquence :

- utilisateur introuvable → `InvalidCredentialsException` (401), sans révéler l'existence ;
- compte déjà verrouillé (avant vérification) → `UserLockedException` (423) ;
- mot de passe erroné → on **persiste** la tentative, puis :
  - si le seuil vient d'être atteint → `UserLockedException` (423) ;
  - sinon → `InvalidCredentialsException` (401) ;
- compte non activé (identifiants pourtant valides) → `AccountNotActivatedException` (403).

## Frontières transactionnelles

Point structurant du handler. Les écritures « échec » et « succès » sont **mutuellement
exclusives** et portent chacune leur propre transaction ; les lectures/gardes ne sont pas
transactionnelles.

La tentative échouée est committée **avant** de lever l'exception de rejet :

```php
$locked = $this->transactional->transactional(function () use ($user, $maxAttempts, $now): bool {
    $user->registerWrongPasswordAttempt($maxAttempts, $now);
    $this->repository->save($user);

    return $user->isLocked();
});

if ($locked) {
    throw new UserLockedException();
}

throw new InvalidCredentialsException();
```

Raison : `TransactionalInterface` (implémentation Doctrine `wrapInTransaction`) **rollback
dès qu'une exception traverse la closure**. Si l'incrément du compteur et le `throw` de rejet
partageaient la même transaction, la sauvegarde serait annulée par le rollback : le compteur
ne persisterait jamais et le compte ne se verrouillerait **jamais** (401 perpétuel au lieu du
423 attendu).

On commit donc la tentative dans sa propre transaction, on en extrait l'état verrouillé
(`isLocked()` évalué juste après la mutation, dans la même portée), puis on lève l'exception
en dehors de toute transaction.

## Exceptions et mapping HTTP

Toutes sont des `UserDomainException` mappées dans `config/packages/api_platform.yaml`
(`exception_to_status`) :

| Exception | Statut |
|---|---|
| `InvalidCredentialsException` | 401 |
| `UserLockedException` | 423 |
| `AccountNotActivatedException` | 403 |

Le corps est un `application/problem+json` dont `detail` reprend le message de l'exception
(ex. `Invalid credentials.`).

## Points d'attention

- Toujours passer par le `CommandBusInterface` ; ne jamais appeler `handle()` hors Application.
- La décision de verrouillage appartient au Domaine (`User`), pas au handler.
- Ne jamais fusionner l'écriture de la tentative échouée et son `throw` dans une même
  transaction : le rollback casserait le lockout.
- L'horodatage passe par `ClockInterface` (testabilité, déterminisme).
- Le seuil est lu via `ConfigInterface` (`app.security.max_login_attempts`), jamais codé en dur.
- La forme publique de la réponse est fixée par la Presentation (`AuthResource`), pas par ce
  read model.
