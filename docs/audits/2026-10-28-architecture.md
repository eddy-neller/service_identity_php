# Audit d'architecture — service_identity, 2026-10-28

> Instantané au commit `2d77407` (« Document the locked re-read of the login handler »,
> 2026-10-28). Non normatif : les règles font foi dans les `AGENTS.md` de couche. Produit par le
> skill `audit-archi` (`back_php/.claude/skills/`) ; précédent : aucun au format daté — les
> évaluations d'août 2026 (`*_eval.md`, supprimées avec ce rapport) notaient sans preuve rejouable
> et décrivaient encore le contexte `Shop`.

## En bref

Les garde-fous sont tous verts : règle de dépendance, forme tactique du Domain, convention CQRS,
purge du cache. Ce qui reste est de la **concurrence** : depuis la correction de `Login`, la règle
« relire ne suffit pas toujours » est écrite, et trois cas d'usage la contournent encore — deux
plafonds d'envoi d'e-mails et la rotation du refresh token, que des requêtes simultanées dépassent.
S'y ajoutent un second cas d'usage mort, voisin de celui supprimé le même jour, et une doc miroir
en retard d'une méthode.

## À corriger

- **`src/Application/User/UseCase/Command/Account/RequestPasswordReset/RequestPasswordResetCommandHandler.php:39`
  — le plafond de 3 e-mails de réinitialisation se dépasse par requêtes simultanées.**
  Règle : `src/Application/AGENTS.md`, « Relire ne suffit pas toujours ». Effet : `findByEmail()`
  lit sans verrou ; N requêtes parallèles lisent toutes `mailSent` < 3 (`User.php:237`) et envoient
  chacune un e-mail. La limite de Kong (5/min sur `/api/users/reset-password`) est par client et ne
  joue que derrière la passerelle : le plafond par compte est le seul de l'agrégat.
  Remède : relire l'utilisateur par `findByIdForUpdate()` dans le callback avant
  `requestPasswordReset()`, modèle `LoginCommandHandler`. (piste `lecture-sans-verrou`)
- **`src/Application/User/UseCase/Command/Onboarding/RequestActivationEmail/RequestActivationEmailCommandHandler.php:39`
  — même course sur le plafond d'e-mails d'activation (`User.php:195`).**
  Règle et remède : identiques. (piste `lecture-sans-verrou`)
- **`src/Application/User/UseCase/Command/Auth/RefreshToken/RefreshTokenCommandHandler.php:36`
  — deux rafraîchissements simultanés du même refresh token obtiennent chacun une paire de jetons.**
  Règle : `src/Application/AGENTS.md`, « Relire ne suffit pas toujours » (jeton à usage unique).
  Effet : `findByHash()` lit sans verrou, les deux transactions trouvent le jeton, le suppriment et
  émettent ; la rotation, qui doit invalider un jeton à son premier usage, ne le fait qu'au second.
  Remède : verrouiller la ligne du jeton (`findByHashForUpdate()` sur
  `RefreshTokenRepositoryInterface`), ou une suppression conditionnelle dont on vérifie qu'elle a
  retiré une ligne. (piste `lecture-sans-verrou`)
- **`src/Application/User/UseCase/Command/Account/ResetWrongPasswordAttempts/ResetWrongPasswordAttemptsCommand.php`
  — cas d'usage mort.** Règle : jugement. Rien dans `src/` ne construit la commande : `Login`
  remet lui-même le compteur à zéro. Comme `RegisterWrongPasswordAttemptCommand`, supprimé le même
  jour (!41), elle écrit le compteur de tentatives sans verrou.
  Remède : supprimer commande, handler et test, et passer le compteur de
  `MessengerHandlerRegistrationTest` à 13. (piste `cas-d-usage-sans-appelant`)
- **`docs/src/Infrastructure/Persistence/Doctrine/User/DoctrineUserRepository.md` — ne décrit pas
  `findByIdForUpdate()`.** Règle : `AGENTS.md`, « Documentation » (`docs/src/` reflète le code).
  Effet : la doc du repository ignore le verrou et le rafraîchissement ajoutés par !40.
  Remède : une section « Relecture verrouillée » (skill `doc-archi`). (piste `doc-en-retard`)

## Tenu

| Règle | Preuve |
|---|---|
| Règle de dépendance | `regle-dependance.sh` : PHPat aucune violation, compléments rien à examiner |
| Forme tactique du Domain | `DomainConventionTest` conforme |
| Convention CQRS, test de chaque handler | `HandlerConventionTest` conforme |
| Cache purgé par les événements | `tags-cache.php` : chaque tag des queries cachées est émis par `DomainEventCacheTags` |
| Transaction et publication | `pistes.php` : aucune `ecriture-hors-transaction`, `publication-hors-transaction` ni `io-sous-transaction` |
| Queries en lecture seule, handlers sans agrégat rendu | `pistes.php` : aucune `query-qui-ecrit` ni `handler-rend-le-domain` |
| Presentation sans décision, Ports consommés | `pistes.php` : aucune `decision-dans-presentation` ni `port-sans-consommateur` |
| Mapper sans réaffectation d'identité | `pistes.php` : aucune `mapper-reaffecte-identite` |
| Unicité de l'email et du nom d'utilisateur | index uniques `UserEmailUniq`, `UserUsernameUniq` (`UserEntity.php:22-23`), traduits en exceptions du Domain par `DoctrineUserRepository` |
| Garde-fous de la pile | les six présents (inventaire) |

Cas tolérés ou légitimes, déjà jugés :

- `lecture-hors-transaction`, `Login` — lecture préalable hors verrou, décisions sur la relecture
  verrouillée (`revue-archi`, cas jugés).
- `lecture-sans-verrou`, `UpdateAvatar`, `UpdatePassword`, `UpdateUserByAdmin`,
  `DeleteUserByAdmin` — chaque requête fixe la valeur qu'elle écrit ; `Logout`,
  `ValidateActivation`, `ConfirmPasswordReset` — rejoués par le même porteur, même effet (idem).
- `ecriture-sans-evenement`, `Logout`, `RefreshToken` — un refresh token n'est lu par aucune query
  cachée ni aucune réaction (idem).
- `decision-dans-handler`, `Login`, `RefreshToken`, `ConfirmPasswordReset`, `ValidateActivation` —
  orchestration autour d'un Port ou d'un lookup (`ddd-tactique`, signaux tranchés).
- `spl-dans-domain`, `ActiveEmail`, `HashedPassword`, `RefreshTokenHash` — états produits en
  interne, jamais par un client (idem).

## Évolution depuis le précédent

Premier rapport daté : toutes les pistes sont nouvelles. Corrigés le même jour, avant l'audit :
la course du compteur de tentatives de `Login` (!40) et `RegisterWrongPasswordAttemptCommand`,
mort (!41).

## Inventaire

| Contexte | Modele | VO | Evenements | Exceptions | Commandes | Queries | Ports | Read models |
|---|---|---|---|---|---|---|---|---|
| User | 2 | 14 | 15 | 23 | 14 | 3 | 10 | 3 |
| Shared | 0 | 2 | 3 | 6 | 0 | 0 | 5 | 1 |

Presentation : 7 ressources, 16 Processors, 3 Providers. Infrastructure : 67 fichiers.
Tests : Application 19, Domain 21, Infrastructure 38, Presentation 24.

Garde-fous : `DependencyRule`, `DomainConventionTest`, `DomainTestCoverageTest`,
`HandlerConventionTest`, `StateTestCoverageTest`, `MessengerHandlerRegistrationTest`.

<!-- empreinte audit-archi
cas-d-usage-sans-appelant	src/Application/User/UseCase/Command/Account/ResetWrongPasswordAttempts/ResetWrongPasswordAttemptsCommand.php	a-corriger
decision-dans-handler	src/Application/User/UseCase/Command/Account/ConfirmPasswordReset/ConfirmPasswordResetCommandHandler.php	legitime
decision-dans-handler	src/Application/User/UseCase/Command/Auth/Login/LoginCommandHandler.php	legitime
decision-dans-handler	src/Application/User/UseCase/Command/Auth/RefreshToken/RefreshTokenCommandHandler.php	legitime
decision-dans-handler	src/Application/User/UseCase/Command/Onboarding/ValidateActivation/ValidateActivationCommandHandler.php	legitime
doc-en-retard	docs/src/Infrastructure/Persistence/Doctrine/User/DoctrineUserRepository.md	a-corriger
ecriture-sans-evenement	src/Application/User/UseCase/Command/Auth/Logout/LogoutCommandHandler.php	tolere
ecriture-sans-evenement	src/Application/User/UseCase/Command/Auth/RefreshToken/RefreshTokenCommandHandler.php	tolere
lecture-hors-transaction	src/Application/User/UseCase/Command/Auth/Login/LoginCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/Account/ConfirmPasswordReset/ConfirmPasswordResetCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/Account/RequestPasswordReset/RequestPasswordResetCommandHandler.php	a-corriger
lecture-sans-verrou	src/Application/User/UseCase/Command/Account/ResetWrongPasswordAttempts/ResetWrongPasswordAttemptsCommandHandler.php	a-corriger
lecture-sans-verrou	src/Application/User/UseCase/Command/Account/UpdateAvatar/UpdateAvatarCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/Account/UpdatePassword/UpdatePasswordCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/Auth/Logout/LogoutCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/Auth/RefreshToken/RefreshTokenCommandHandler.php	a-corriger
lecture-sans-verrou	src/Application/User/UseCase/Command/Onboarding/RequestActivationEmail/RequestActivationEmailCommandHandler.php	a-corriger
lecture-sans-verrou	src/Application/User/UseCase/Command/Onboarding/ValidateActivation/ValidateActivationCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/UserManagement/DeleteUserByAdmin/DeleteUserByAdminCommandHandler.php	tolere
lecture-sans-verrou	src/Application/User/UseCase/Command/UserManagement/UpdateUserByAdmin/UpdateUserByAdminCommandHandler.php	tolere
spl-dans-domain	src/Domain/User/ValueObject/Identity/ActiveEmail.php	legitime
spl-dans-domain	src/Domain/User/ValueObject/Security/HashedPassword.php	legitime
spl-dans-domain	src/Domain/User/ValueObject/Security/RefreshTokenHash.php	legitime
-->
