# UserNotifier

Fichier documenté :
`infrastructure/src/Notification/User/UserNotifier.php`.

## Rôle

`UserNotifier` prépare et remet les e-mails utilisateur portant un token :
l'activation de compte et la réinitialisation de mot de passe. Il s'exécute dans
le worker `domain_events` ; le lien contenant le token reste donc en mémoire et
n'est jamais sérialisé dans RabbitMQ ni dans le transport `failed_domain_events`.

C'est un adaptateur purement Infrastructure. Son interface
`UserNotifierInterface` reste dans cette couche car seuls les handlers
d'événements Infrastructure la consomment ; ce n'est donc pas un Port de la
couche Application.

## Chaîne de traitement

```text
ActivationEmailRequestedEvent / PasswordResetRequestedEvent
                         |
                         v
SendActivationEmailHandler / SendResetPasswordEmailHandler
       (relit et encode le token courant)
                         |
                         v
                    UserNotifier
                         |
                         v
                 LockFactory (Redis)
                         |
                         v
                Mailer -> SMTP directement
```

Les handlers relisent le token courant de l'agrégat, vérifient son existence et
son expiration, puis l'encodent avec `TokenProviderInterface`. Un token absent,
remplacé ou expiré, ou un utilisateur absent, est un no-op métier : le handler
le journalise, le marque dans le ledger et ne contacte pas le notifier.

`UserNotifier` reçoit donc un `User` et un token déjà encodé. Il construit le
lien uniquement pour rendre le template et délègue immédiatement l'envoi à
`Mailer`. `config/packages/mailer.yaml` désactive le bus interne du Mailer
(`message_bus: false`) : aucune seconde enveloppe contenant l'e-mail rendu ne
peut être ajoutée à une file.

Le message technique `SendEmailMessage`, routé vers `async`, reste utilisé
uniquement pour le formulaire de contact. Il ne porte aucun token d'accès.

## Contenu de l'e-mail

| Cas | Sujet traduit | Template | Paramètre de lien front |
|---|---|---|---|
| Activation | `user.register.activation.title` | `emails/user/register-activation.html.twig` | `mailerFrontLinkRegisterValidation` |
| Réinitialisation | `user.reset.password.title` | `emails/user/reset-password.html.twig` | `mailerFrontLinkResetPassword` |

Le contexte transmis au template est volontairement réduit à `username`,
`link` et `userLocale`. Le lien est composé avec le token encodé, encodé une
seconde fois comme valeur de paramètre de requête via `urlencode()`.

Le sujet est traduit dans le domaine `messages` avec la langue préférée de
l'utilisateur. Cette langue n'est utilisée que si elle figure dans
`app.enabled_locales`; sinon, y compris si ce paramètre est mal configuré et
n'est pas un tableau, le notifier utilise `app.default_locale`. Les paramètres
sont déclarés dans
[`config/services/parameters.yaml`](../../../../../config/services/parameters.yaml).

## Déduplication des e-mails à token

Chaque envoi prend un verrou Redis de 300 secondes. La clé est :

```text
user.<canal>.<userId>.<32 premiers caractères du SHA-256 du token encodé>
```

Les canaux sont `activation_email` et `password_reset_email`. Ils isolent les
deux cas afin qu'une demande de réinitialisation ne puisse pas supprimer un
e-mail d'activation concurrent.

Le token fait partie de la clé parce qu'il est régénéré à chaque demande. Deux
envois avec le même token sont de vrais doublons, tandis qu'un nouveau token
produit une clé distincte et son e-mail ne peut pas être écarté au profit d'un
lien devenu invalide. Le token est haché : la clé de verrou est stockée par
Redis et ne doit pas l'exposer en clair.

Si le verrou est déjà détenu, `UserNotifier` produit un log `info` avec
l'identifiant utilisateur et le template, puis n'envoie rien. Sinon, il le
relâche dans un `finally`, y compris si le SMTP échoue. L'exception remonte alors
au worker `domain_events`, qui applique ses retries au Domain Event ne
contenant pas de secret.

Cette protection couvre les rafales concurrentes, pas la limitation du nombre
de demandes dans le temps. Elle complète l'idempotence des handlers
d'événements (ledger), sans la remplacer.

## Limites et responsabilités

`UserNotifier` ne :

- génère, ne valide et ne persiste aucun token ;
- ne décide pas qu'un e-mail doit être envoyé ;
- ne garantit pas une livraison unique : un crash entre la remise SMTP et le
  marquage du ledger peut toujours provoquer un doublon ;
- ne gère pas lui-même les retries : les erreurs de rendu ou de remise remontent
  au worker `domain_events`.

## Vérification

Les tests unitaires de
`infrastructure/tests/Unit/Notification/User/UserNotifierTest.php` vérifient :

- le contenu de chaque e-mail remis à `Mailer` ;
- la résolution de langue et son fallback ;
- la forme, la séparation par canal et la rotation des clés de verrou ;
- l'absence d'envoi quand un verrou identique est déjà détenu.

Exécuter cette couverture avec `make unit-suite s=infra.notif`.
