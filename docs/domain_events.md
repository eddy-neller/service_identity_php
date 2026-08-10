# Domain Events

Cette API publie ses Domain Events dans un **outbox transactionnel** porté par Doctrine, et
les consomme dans un worker Messenger séparé. Ce document décrit le cycle de vie complet :
de l'enregistrement d'un fait métier dans un agrégat jusqu'à l'exécution de ses réactions,
en passant par la garantie d'atomicité et la déduplication des redélivrances.

Pour la mécanique CQRS elle-même (bus de commandes/requêtes, middlewares, adapters), voir
[`CQRS_messenger.md`](CQRS_messenger.md).

## Le problème résolu

Auparavant, un command handler persistait son agrégat, **puis** publiait ses événements via
l'`EventDispatcher` Symfony, qui exécutait les réactions en ligne dans la requête HTTP :

```php
$user = $this->transactional->transactional(fn () => ...);   // COMMIT
$this->eventDispatcher->dispatchAll($user->releaseEvents()); // hors transaction
```

Deux défauts structurels :

1. **Perte silencieuse.** Si une réaction échouait — Redis indisponible, base occupée, mailer
   injoignable — l'utilisateur était déjà commité, mais son `Customer` n'était jamais créé et
   l'e-mail jamais envoyé. Aucune trace, aucune reprise possible.
2. **Latence facturée au client.** L'appelant HTTP payait le temps de toutes les réactions.

L'outbox règle les deux : l'événement est écrit **dans la même transaction** que l'agrégat,
et les réactions sont exécutées plus tard, avec retries.

## Vue d'ensemble

```text
┌─ Command handler (Application) ──────────────────────────────┐
│                                                              │
│  $this->transactional->transactional(function () {           │
│      $this->repository->save($user);              ──┐        │
│      $this->eventBus->publishAll(                   │ même   │
│          $user->releaseEvents()                     │ trans- │
│      );                                           ──┘ action │
│  });                                                         │
│                          │                                   │
└──────────────────────────┼───────────────────────────────────┘
                           │ COMMIT atomique
                           ▼
              ┌────────────────────────────────────────────────┐
              │  CacheInvalidationMiddleware  (command.bus)    │
              │  purge les tags de cache, avant la réponse     │
              └────────────┬───────────────────────────────────┘
                           ▼
              ┌────────────────────────────┐
              │  messenger_messages        │  outbox Doctrine
              │  queue_name=domain_events  │
              └────────────┬───────────────┘
                           │  messenger:consume domain_events
                           ▼
┌─ Worker (Infrastructure) ────────────────────────────────────┐
│  LogDomainEventHandler           tous les événements         │
│  ProvisionCustomerHandler        registered, created_by_admin│
│  DisableCustomerHandler          deleted                     │
│  SendActivationEmailHandler      activation_email.requested  │
│  SendResetPasswordEmailHandler   password_reset.requested    │
│  RevokeSessionsHandler           reauthentication.required   │
│                     │                                        │
│                     ▼                                        │
│         processed_domain_event  (inbox / déduplication)      │
└──────────────────────────────────────────────────────────────┘
                           │ échec définitif après 6 tentatives (~10 min)
                           ▼
              ┌───────────────────────────────────┐
              │  queue_name=failed_domain_events   │
              └───────────────────────────────────┘
```

## 1. Enregistrer un fait métier

Les événements vivent dans `src/Domain/<Context>/Event/`. Ils sont `final readonly`, ne
dépendent d'aucun framework, et implémentent `DomainEventInterface` :

```php
interface DomainEventInterface
{
    public function eventId(): string;      // identité stable, clé de déduplication
    public function aggregateId(): string;  // sujet de l'événement, pour corréler les journaux
    public function occurredOn(): DateTimeImmutable;
    public function eventName(): string;    // 'user.registered'
}
```

`DomainEventIdentityTrait` (SharedKernel) fournit `eventId()` et le générateur. L'événement
l'assigne **dans son constructeur** :

```php
final readonly class UserRegisteredEvent implements UserDomainEventInterface
{
    use DomainEventIdentityTrait;

    public function __construct(
        private UserId $userId,
        private DateTimeImmutable $occurredOn,
    ) {
        $this->eventId = self::generateEventId();
    }
    // ...
}
```

Les 13 événements du contexte User implémentent `UserDomainEventInterface`, qui étend
`DomainEventInterface` en rendant contractuel l'accès au `UserId`. Un consommateur qui doit
réagir à *tout* fait touchant un compte — l'invalidation de cache, par exemple — se type dessus
au lieu d'énumérer les classes une par une.

`generateEventId()` fait `bin2hex(random_bytes(16))` — du PHP core, donc aucune dépendance
Symfony ni Ramsey n'entre dans le Domain. L'identifiant est fixé **une seule fois**, à la
construction, et voyage avec l'événement lors de la sérialisation : il reste identique à
chaque redélivrance du même message. C'est précisément ce qui rend la déduplication possible.

`aggregateId()` est implémenté par chaque événement et retourne l'identifiant de son sujet
(`$this->userId->toString()` pour les événements User, `$this->orderId->toString()` pour Ordering).
Il sert à corréler les journaux : retrouver toutes les réactions déclenchées par un utilisateur
donné se fait par cet identifiant technique, jamais par une donnée personnelle (voir ci-dessous).

L'agrégat enregistre ses événements via `DomainEventTrait` :

```php
$this->recordEvent(new UserRegisteredEvent($id, $now));
// ...
$events = $user->releaseEvents(); // récupère ET vide la liste
```

### Ce qu'un événement ne doit pas transporter

**Aucun secret.** Un événement est persisté en clair dans `messenger_messages`, et y reste
tant qu'il n'est pas consommé — voire indéfiniment dans `failed_domain_events`. Les tokens
d'activation et de réinitialisation ne sont donc **pas** portés par les événements : les
handlers les relisent sur l'agrégat et remettent l'e-mail directement depuis le worker, sans
construire de message technique persisté (voir §6).

**Aucune donnée personnelle non nécessaire.** Un événement **peut** en porter une, à condition
qu'une réaction en ait réellement besoin : `ActivationEmailRequestedEvent` et
`PasswordResetRequestedEvent` transportent une `EmailAddress` parce que
`SendActivationEmailHandler` et `SendResetPasswordEmailHandler` la passent à
`TokenProviderInterface::encode()`, qui lie le token à son destinataire.

Le test de référence est donc « quel consommateur la lit ? ». `UserRegisteredEvent` et
`UserCreatedByAdminEvent` transportaient eux aussi une `EmailAddress`, dont plus personne ne se
servait depuis la disparition du subscriber synchrone qui la journalisait : elle a été retirée.
Une donnée personnelle sans consommateur n'a rien à faire dans un message persisté.

Quand elle est justifiée, la règle porte sur ce qu'on en fait : elle ne doit pas ressortir dans
les journaux, qui sont agrégés, conservés et souvent exportés. C'est le rôle d'`aggregateId()` —
identifier le sujet sans l'exposer.

### Événements existants

| Événement | `eventName()` | Réaction |
|---|---|---|
| `UserRegisteredEvent` | `user.registered` | provisioning Customer |
| `UserCreatedByAdminEvent` | `user.created_by_admin` | provisioning Customer |
| `UserDeletedByAdminEvent` | `user.deleted` | désactivation Customer |
| `ActivationEmailRequestedEvent` | `user.activation_email.requested` | e-mail d'activation |
| `PasswordResetRequestedEvent` | `user.password_reset.requested` | e-mail de réinitialisation |
| `UserReauthenticationRequiredEvent` | `user.reauthentication.required` | révocation des sessions |
| `UserActivatedEvent` | `user.activated` | — |
| `UserUpdatedByAdminEvent` | `user.updated_by_admin` | — |
| `UserAvatarUpdatedEvent` | `user.avatar_updated` | — |
| `UserPasswordUpdatedEvent` | `user.password.updated` | — |
| `PasswordResetCompletedEvent` | `user.password_reset.completed` | — |
| `UserWrongPasswordAttemptRegisteredEvent` | `user.wrong_password_attempt.registered` | — |
| `UserWrongPasswordAttemptsResetEvent` | `user.wrong_password_attempts.reset` | — |
| `OrderPlacedEvent` | `shop.order.placed` | *non publié* — Ordering non extrait |
| `OrderPaidEvent` | `shop.order.paid` | *non publié* — Ordering non extrait |

Les 13 événements User déclenchent en outre, systématiquement, la journalisation
(`LogDomainEventHandler`) dans le worker et l'invalidation du cache de queries
(`CacheInvalidationMiddleware`) dans la requête : la colonne ci-dessus ne liste que les
réactions spécifiques.

Les deux événements `Ordering` existent dans le Domain mais ne sont émis par aucun use case :
l'écriture des commandes passe encore par le legacy `src/Entity/Shop/Order.php`. Ils seront
raccordés automatiquement (le routage cible l'interface) le jour où le use case sera extrait.

## 2. Publier dans la transaction

L'Application publie via un Port, `DomainEventBusInterface` :

```php
interface DomainEventBusInterface
{
    /** @param DomainEventInterface[] $events */
    public function publishAll(array $events): void;
}
```

**La règle** : `publishAll()` est appelé **à l'intérieur** du callback
`TransactionalInterface::transactional()`, juste après le `save()` (ou le `delete()`) de
l'agrégat.

```php
$user = $this->transactional->transactional(function () use ($user): User {
    $this->uniquenessChecker->ensureEmailAndUsernameAvailable($email, $username);

    $this->repository->save($user);
    $this->eventBus->publishAll($user->releaseEvents());

    return $user;
});

return UserItem::fromUser($user);
```

L'implémentation `MessengerDomainEventBus` dispatche sur `event.bus`, routé vers le transport
`domain_events`. Le transport Doctrine émet son `INSERT` sur **la connexion Doctrine courante**,
donc dans la transaction ouverte par le handler : l'agrégat et ses événements sont commités
ensemble, ou pas du tout.

Le dispatch s'arrête au `SendMessageMiddleware` de Messenger — aucun handler n'est invoqué
pendant la requête HTTP. C'est pourquoi `MessengerDomainEventBus` ne lit aucun `HandledStamp`,
contrairement à `MessengerCommandBus`.

### Pourquoi pas seulement `DispatchAfterCurrentBusStamp` ?

`DispatchAfterCurrentBusStamp` est utile lorsqu'un handler dispatche directement un second
message : Messenger diffère alors son traitement jusqu'à la réussite du message courant. Il
évite notamment qu'un handler synchrone puisse traiter un message enfant avant que son parent
ait fini.

Il ne remplace toutefois pas l'outbox transactionnel. Le stamp ordonne deux dispatches dans le
processus courant, mais ne rend pas atomiques le `COMMIT` de la base et l'envoi vers un transport.
Après un `COMMIT` réussi, un arrêt du processus avant le dispatch différé peut perdre le message ;
à l'inverse, un message remis à un transport avant le commit peut survivre à son rollback.

Ici, l'`INSERT` de l'agrégat et celui de l'événement dans `messenger_messages` appartiennent à
la même transaction : un rollback supprime les deux, et le worker ne peut lire l'événement
qu'après le commit. Les e-mails ne sont ensuite dispatchés qu'à la consommation de cet événement,
donc après la création durable de l'utilisateur. `DispatchAfterCurrentBusStamp` reste pertinent
pour un besoin local d'ordonnancement, mais pas comme mécanisme de fiabilité de ce flux.

**Corollaire** : la publication ne doit jamais déclencher d'I/O externe. Un appel HTTP ou un
envoi d'e-mail dans le chemin de publication rallongerait la transaction et rendrait le commit
dépendant d'un service tiers.

## 3. L'outbox Doctrine

```yaml
# config/packages/messenger.yaml
domain_events:
  dsn: "doctrine://default?queue_name=domain_events&auto_setup=false"
  options:
    use_notify: false
  retry_strategy:
    max_retries: 6
    delay: 10000
    multiplier: 2
    max_delay: 300000
    jitter: 0.1
```

Deux réglages ne sont pas cosmétiques :

- **`auto_setup=false`, dans tous les environnements.** En PostgreSQL le DDL est transactionnel :
  si Messenger déclenchait la création de `messenger_messages` à l'intérieur d'une transaction
  métier, un rollback ferait disparaître la table avec elle. Les tables viennent donc des
  migrations (`Version20260806120000`), jamais de l'auto-setup.
- **`use_notify: false`.** Le transport PostgreSQL utilise `LISTEN`/`NOTIFY` via un trigger que
  seul `auto_setup` sait créer. Comme l'auto-setup est désactivé, on coupe la notification et le
  worker retombe sur le polling. Attention : `use_notify` est une **option de transport**, pas un
  paramètre de DSN — le mettre dans l'URL fait échouer le démarrage.

Le bus lui-même :

```yaml
event.bus:
  default_middleware:
    allow_no_handlers: true
```

- `allow_no_handlers: true` : un événement sans réaction dédiée ne doit pas faire échouer le
  worker. On peut donc émettre un fait métier avant d'écrire la réaction qui l'exploitera.
- **Pas de `UnwrapHandlerFailedExceptionMiddleware`** sur ce bus, contrairement à `command.bus` et
  `query.bus` : Messenger s'appuie sur `HandlerFailedException` pour arbitrer retry / échec
  définitif. Déballer l'exception dégraderait la stratégie de retry.

Le routage cible l'**interface**, donc tout nouvel événement est couvert sans configuration :

```yaml
routing:
  App\Domain\SharedKernel\Event\DomainEventInterface: domain_events
```

### Tables

| Table | Rôle |
|---|---|
| `messenger_messages` | outbox (`queue_name = 'domain_events'`) et files d'échec (`failed_async`, `failed_domain_events`) |
| `processed_domain_event` | inbox : couples (`event_id`, `handler`) déjà traités, clé primaire composite |

`processed_domain_event` n'est pas une entité ORM. `config/packages/doctrine.yaml` l'exclut de
l'introspection (`schema_filter`) pour que `doctrine:migrations:diff` ne propose pas de la
supprimer. `messenger_messages`, elle, est ajoutée au schéma par le listener de
`symfony/doctrine-messenger` et suit donc les diffs normalement.

## 4. Le worker et ses handlers

```bash
make messenger-consume-events
# bin/console messenger:consume domain_events -vv --time-limit=3600 --memory-limit=256M --limit=100 --failure-limit=5
```

Le conteneur `app` lance les workers via Supervisor. Chaque processus est relancé automatiquement
après son message en cours lorsqu'il atteint une de ces bornes : une heure, 256 Mo, 100 messages ou
5 échecs. Les limites empêchent un worker Doctrine long-vivant d'accumuler mémoire, connexions ou
état dégradé. Le worker `async` applique le même temps et seuil d'échecs, avec 128 Mo et 200 messages.

Lors d'un déploiement, demander d'abord l'arrêt propre avec `make messenger-stop-workers` : Messenger
termine le message en cours, puis Supervisor redémarre le processus. L'image doit être reconstruite et
le conteneur `app` recréé lorsque `docker/app/supervisor/conf.d/messenger-worker.conf` change, car ce
fichier est copié dans l'image. Surveiller aussi
`messenger:failed:show --transport=failed_domain_events`.

Les handlers vivent dans `src/Infrastructure/Messenger/Event/Handler/`, un par réaction, annotés
`#[AsMessageHandler(bus: 'event.bus')]`. Un handler peut couvrir plusieurs événements en portant
l'attribut sur plusieurs méthodes (`ProvisionCustomerHandler`).

`LogDomainEventHandler` est typé sur `DomainEventInterface` : il journalise **tout** événement
consommé sans qu'aucune déclaration ne soit nécessaire pour un nouvel événement.

```php
$this->logger->info('Domain event handled', [
    'event' => $event->eventName(),
    'event_id' => $event->eventId(),
    'aggregate_id' => $event->aggregateId(),
    'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s'),
]);
```

Un handler de réaction n'a donc **pas** à re-journaliser le fait : ce serait une ligne en double,
et sémantiquement fausse à cet endroit. Il journalise sa propre réaction quand elle mérite une
trace — `ProvisionCustomerHandler` logue par exemple le cas « Customer déjà provisionné », qui
n'est pas déductible de l'événement seul.

L'invalidation du cache de queries, elle, ne passe **pas** par un handler de réaction : elle vit
dans le `CacheInvalidationMiddleware` du `command.bus` (§7). `DomainEventCacheTags` y applique le
même principe de typage large, sur `UserDomainEventInterface` : tout fait touchant un compte
purge `users-collection` et `user-<id>`. C'est la raison d'être de cette interface — sans elle, il
faudrait réénumérer les 13 événements User à chaque ajout.

Les handlers restent de l'**adaptation** : quand une réaction est un vrai cas d'usage métier, ils
passent par le `CommandBusInterface` (`ProvisionCustomerHandler` dispatche
`CreateCustomerCommand`) plutôt que de manipuler les agrégats eux-mêmes.

## 5. Idempotence

Les retries de Messenger garantissent l'**au-moins-une-fois**, jamais l'exactement-une-fois. La
table `processed_domain_event` mémorise les réactions déjà exécutées, via
`DomainEventLedgerInterface` (contrat interne à Infrastructure, pas un Port — aucun use case ne
le consomme) :

```php
public function hasProcessed(string $eventId, string $handler): bool;
public function markProcessed(string $eventId, string $handler): void;
```

`markProcessed()` insère en `ON CONFLICT DO NOTHING` : deux workers traitant la même
redélivrance en parallèle sont arbitrés par la clé primaire composite, le perdant n'a rien à
faire.

Trois régimes, selon la nature de l'effet :

| Régime | Handlers | Mécanique | Garantie |
|---|---|---|---|
| **Effet en base** | `ProvisionCustomerHandler`, `DisableCustomerHandler` | `hasProcessed()` puis effet puis `markProcessed()`, le tout dans **une même transaction** | exactement-une-fois réel : si l'effet est annulé, la trace l'est aussi |
| **Effet externe** | `SendActivationEmailHandler`, `SendResetPasswordEmailHandler`, `RevokeSessionsHandler` | `hasProcessed()` en garde d'entrée, `markProcessed()` **après** succès | au-moins-une-fois, dédupliqué sur le chemin nominal |
| **Journalisation** | `LogDomainEventHandler` | aucun ledger | idempotent par nature |

**Limite assumée** : un crash entre l'envoi d'un e-mail et son marquage produit un doublon. Le
véritable exactement-une-fois côté fournisseur e-mail exigerait une clé d'idempotence supportée
par celui-ci ; la ledger ne peut pas le simuler.

### Pourquoi une table plutôt que `DeduplicateStamp`

Symfony fournit un `DeduplicateStamp` et son `DeduplicateMiddleware`, activé automatiquement dès
que le composant Lock est configuré. Ils sont disponibles ici — le middleware est même déjà présent
dans les quatre chaînes de bus — et ils **ne conviennent pas** pour cet usage. Ce n'est pas un
choix par défaut mais par nature : les deux mécanismes ne résolvent pas le même problème.

En lisant `DeduplicateMiddleware::handle()` :

- **à l'émission** (pas de `ReceivedStamp`) il acquiert le verrou ; s'il échoue, il fait
  `return $envelope;` — le message n'est jamais envoyé ;
- **à la consommation** il ne teste rien, il relâche le verrou après traitement.

Il empêche donc qu'un **second message** portant la même clé soit émis pendant qu'un premier est
en vol : c'est de l'exclusion mutuelle, pas de l'idempotence.

Or le scénario qui nous occupe est la **redélivrance du même message**. Un worker meurt après avoir
créé le Customer mais avant l'ack, `redeliver_timeout` remet la ligne en file, le message repasse —
et il porte alors un `ReceivedStamp`, donc le middleware se contente de relâcher le verrou. Les
handlers rejouent intégralement. Le verrou n'a rien empêché.

Quatre écarts, chacun rédhibitoire :

| | `DeduplicateStamp` | ledger `processed_domain_event` |
|---|---|---|
| Durée | TTL (300 s par défaut) | permanente |
| Granularité | l'enveloppe | le couple (`event_id`, `handler`) |
| Atomicité avec l'effet | impossible : relâcher un verrou n'est pas transactionnel | `markProcessed()` dans la transaction de l'effet |
| Collision | message écarté silencieusement | no-op tracé |

Le TTL suffirait à trancher : les retries suivent un backoff exponentiel plafonné, et un
message rejoué depuis `failed_domain_events` via `messenger:failed:retry` peut l'être des jours plus tard. Le
verrou a expiré depuis longtemps.

La granularité aussi : `UserRegisteredEvent` déclenche trois handlers (journal, invalidation de
cache, provisioning). Si seul le provisioning échoue, on veut rejouer celui-là — un verrou unique
par enveloppe ne sait pas l'exprimer.

Et surtout l'atomicité, qui est la raison d'être du dispositif : `markProcessed()` partage la
transaction de la création du Customer, donc si elle rollback la trace disparaît avec elle. Aucun
verrou ne peut offrir ça.

S'ajoute une incompatibilité propre à notre chaîne : `publishAll()` est appelé **à l'intérieur** de
la transaction métier. Y poser un verrou violerait la règle « pas d'I/O externe dans le chemin de
publication » (§2), et un rollback ultérieur laisserait le verrou pris pour tout son TTL — ce qui
ferait taire silencieusement la republication légitime du même événement.

`DeduplicateStamp` reste le bon outil pour dédoublonner une enveloppe Messenger transportée.
Les e-mails à token n'en utilisent toutefois pas : le token ne doit pas être porté par une
enveloppe persistée.

### Déduplication à l'émission des e-mails à token

La ledger dédoublonne les **réactions**. Un cran plus loin, `UserNotifier` prend un verrou Symfony
Redis juste avant de remettre les deux e-mails porteurs d'un token au SMTP. Un second envoi portant
la même clé pendant ce laps de temps est **écarté sans être envoyé**. Le lien reste en mémoire dans
le worker `domain_events` et ne passe ni par RabbitMQ, ni par `failed_domain_events`.

```text
user.<canal>.<userId>.<sha256 tronqué du token>
   canaux : activation_email · password_reset_email
```

Trois composants, trois raisons :

- **le canal**, pour qu'une demande de réinitialisation n'annule jamais un e-mail d'activation émis
  au même instant ;
- **l'identifiant utilisateur**, périmètre naturel de la déduplication ;
- **le token**, et c'est lui qui rend le dispositif sûr. `User::requestActivation()` et
  `User::requestPasswordReset()` **remplacent le token** à chaque demande, et les handlers relisent
  le token courant au moment où ils traitent l'événement. Avec une clé réduite à l'utilisateur, une
  demande arrivant pendant l'envoi du mail précédent verrait son propre mail écarté : l'utilisateur
  ne recevrait que le mail antérieur, porteur d'un token déjà remplacé — un **lien mort sans
  suivi**. En intégrant le token, deux mails portant le même token sont de vrais doublons et le
  second est écarté, tandis qu'un token régénéré produit une clé différente et part toujours.

Le token est haché parce que la clé devient un identifiant de verrou stocké tel quel dans Redis.

Deux conséquences opérationnelles :

- **`LOCK_DSN` doit pointer sur Redis, pas sur `flock`.** Le verrou doit être partagé entre les
  workers et les conteneurs ; `flock` ne garantit pas cette coordination distribuée. Les verrous
  sont isolés en base 1 (`LOCK_DSN="${REDIS_URL}/1"`).
- **L'envoi écarté ne lève aucune exception.** `UserNotifier` journalise
  `Duplicate user mail discarded…` — sans cette trace, un envoi supprimé serait indiscernable d'un
  envoi perdu.

Ce mécanisme protège des rafales, **pas d'une cadence** : le verrou est relâché après chaque
tentative d'envoi, y compris en cas d'erreur, afin que le retry du Domain Event puisse reprendre.
Un véritable plafond par fenêtre relève des limiteurs déclarés dans
`config/packages/rate_limiter.yaml` (`register_activation_email` et `reset_password_email`,
3 / 30 min), qui ne sont aujourd'hui **consommés par aucun code**.

### Idempotence métier, en plus du ledger

Le ledger n'est pas la seule ligne de défense. `ProvisionCustomerHandler` attrape
`CustomerAlreadyExistsException` et la traite comme un succès :

```php
try {
    $this->commandBus->dispatch(new CreateCustomerCommand($userId));
} catch (CustomerAlreadyExistsException) {
    // Redélivrance, ou création concurrente arbitrée par la contrainte unique
    // sur shop_customer.user_account_id. La réaction est satisfaite.
}
$this->ledger->markProcessed($eventId, self::class);
```

Deux points importants :

- La sémantique de `CreateCustomerCommand` est **inchangée** : elle continue de lever une
  exception de conflit, ce qui porte le 409 de `CustomerPostProcessor` côté API. C'est le
  handler d'événement qui décide que, dans son contexte, le conflit est un succès.
- L'unicité est garantie en base (`shop_customer.user_account_id` en `unique: true`), donc la
  concurrence réelle est couverte même si deux workers passent simultanément le contrôle
  d'existence : le perdant échoue, Messenger retente, et la seconde tentative trouve le Customer.

## 6. Échecs et données obsolètes

### Retries

`domain_events` retente 6 fois avec des délais de 10 s, 20 s, 40 s, 80 s, 160 s puis 300 s
(± 10 % de jitter), soit environ 10 minutes avant l'échec définitif. Cette fenêtre reste
inférieure à la validité de 15 minutes d'un token de réinitialisation. Après épuisement, le message
part dans le transport `failed_domain_events` (même table) et reste inspectable :

```bash
bin/console messenger:failed:show --transport=failed_domain_events
bin/console messenger:failed:show --transport=failed_domain_events <id> -vv
bin/console messenger:failed:retry --transport=failed_domain_events
```

### Échec définitif : `UnrecoverableMessageHandlingException`

Certaines situations ne sont pas rattrapables par un retry. Les relancer plusieurs fois ne fait que
polluer les logs et retarder la file. Les handlers doivent alors lever
`UnrecoverableMessageHandlingException`, qui envoie directement le message dans
`failed_domain_events`. Cette exception est réservée à un message réellement invalide, pas à un état
métier normal.

Les handlers d'e-mail relisent le token courant sur l'agrégat plutôt que de le recevoir dans
l'événement, puis remettent directement l'e-mail au SMTP depuis le worker (voir §1 : aucun secret
dans un transport). En asynchrone, un événement rejoué tardivement peut tomber sur un token
remplacé, expiré, ou sur un utilisateur supprimé. C'est un no-op métier attendu : le handler le
journalise, marque son ledger, puis retourne normalement.

```php
if (null === $token || null === $tokenTtl || $tokenTtl < $this->clock->now()->getTimestamp()) {
    $this->logger->info('Activation email skipped because its token is stale.', [...]);
    $this->ledger->markProcessed($event->eventId(), self::class);

    return;
}
```

**Conséquence fonctionnelle à connaître** : si un utilisateur demande deux fois son e-mail
d'activation coup sur coup, le second token écrase le premier ; le premier événement, s'il est
traité après, est acquitté sans e-mail et sans alimenter `failed_domain_events`. C'est le comportement voulu.

### Le cache de queries n'attend pas le worker

L'invalidation du cache est la seule réaction que l'outbox ne peut pas porter : un client qui relit
juste après son écriture — le front qui recharge `/api/users/me` après un upload d'avatar — arrive
avant le réveil du worker et reçoit la version cachée d'avant, pour tout le TTL restant.

Elle vit donc dans la requête, dans le `CacheInvalidationMiddleware` du `command.bus` :

```text
command.bus ─→ CacheInvalidationMiddleware ─→ … ─→ handler
                            │                        │ transactional() { … } → COMMIT
                            └────────────────────────┘ purge des tags, au retour
```

- `MessengerDomainEventBus` remet chaque événement publié au `PublishedDomainEventCollector`,
  en plus de l'écrire dans l'outbox.
- Le middleware, placé **autour** du handler, s'exécute après son `commit` : les tags sont purgés
  quand la donnée est visible en base. Purger avant le commit serait pire que tardif — un lecteur
  concurrent recacherait l'état d'avant l'écriture.
- `DomainEventCacheTags` fait la correspondance événement → tags.
- Le collecteur est vidé même quand la commande lève : un worker traite les messages en série,
  aucun événement ne doit fuir vers le suivant.

C'est **exhaustif sans être redondant** : les 13 publieurs sont tous des command handlers, donc
tous traversent ce middleware. Un handler d'invalidation branché en plus sur l'outbox n'attraperait
rien de mieux, et laisserait croire que cette purge tolère un délai — ce qui est précisément le bug.

Corollaire : les queries Shop (`DisplayListProduct`, `DisplayListCategory`, `DisplayMyCustomer`)
sont cachées sans qu'aucun événement ne les invalide, faute de `ShopDomainEventInterface`. Elles
dépendent aujourd'hui de leur seul TTL.

## 7. Sérialisation

Les événements passent par le `PhpSerializer` de Messenger. Il gère nativement les Value Objects
et les enums qu'ils transportent (`EmailAddress`, `UserId`, `ReauthenticationReason`), sans aucun
normalizer à écrire.

Les handlers du bus `event.bus` demandent aussi `sign: true`. Le serializer ajoute une signature HMAC
SHA-256 du corps avec `APP_SECRET`, vérifiée avant décodage par le worker. La signature prévient une
injection ou une altération dans l'outbox, mais ne chiffre pas le message : la règle « aucun secret
dans un Domain Event » reste impérative. Avant d'activer cette version, drainer `domain_events` et
`failed_domain_events`, puis garder `APP_SECRET` stable tant que des messages signés restent en file.

**Contrepartie, qui est une vraie contrainte d'exploitation** : renommer ou déplacer une classe
d'événement — ou un Value Object qu'elle porte — rend illisibles les messages déjà en file.

> **Règle** : vider `domain_events` et `failed_domain_events` avant tout renommage ou déplacement d'une classe
> d'événement ou d'un VO transporté. En pratique : arrêter les émissions, laisser le worker
> drainer la file, puis refactorer.

## 8. Environnement de test

En test, `domain_events` est remplacé par `sync://` : les réactions s'exécutent en ligne, au
dispatch. Les tests API qui vérifient un effet de bord juste après un appel (par exemple le
`Customer` créé après un register) restent donc valides sans modification.

L'écart avec la production est assumé et connu : en `sync://`, les handlers tournent **dans** la
transaction englobante, donc un échec de handler y annule l'écriture métier — ce que la
production ne fait pas. Les tests d'intégration ci-dessous couvrent le comportement réel.

| Suite | Périmètre |
|---|---|
| `infra.messenger.event` | bus et handlers en isolation, avec doubles (`tests/Unit/Messenger/Event`) |
| `infra.outbox` | atomicité et ledger avec un Doctrine réel (`tests/Integration/Messenger`) |
| `domain.shared` | stabilité de `eventId` à travers la sérialisation |
| `appli.user` | publication effectuée **dans** le callback transactionnel |

`DomainEventOutboxTest` reconstruit un vrai transport Doctrine (puisque l'environnement de test
route vers `sync://`) et vérifie trois propriétés : la ligne outbox est visible dans la
transaction avant le commit, elle disparaît au rollback, et le message stocké restitue
`eventId()` ainsi que ses Value Objects après désérialisation.

## 9. Recettes

### Ajouter un nouvel événement

1. Créer la classe dans `src/Domain/<Context>/Event/<Catégorie>/`, `final readonly`, avec
   `DomainEventIdentityTrait` et `$this->eventId = self::generateEventId();` au constructeur.
   Implémenter `aggregateId()`, `occurredOn()` et `eventName()`. Pour le contexte User,
   implémenter `UserDomainEventInterface` plutôt que `DomainEventInterface` — sinon l'invalidation
   de cache ne se déclenchera pas.
2. L'enregistrer depuis l'agrégat via `recordEvent()`, dans la méthode métier concernée.
3. Vérifier que le use case appelle bien `publishAll($aggregate->releaseEvents())` **dans** son
   callback transactionnel.
4. Rien à configurer : le routage cible `DomainEventInterface`, et `LogDomainEventHandler` le
   journalisera déjà.
5. Tester l'enregistrement côté Domain (`domain.<context>`) et la publication côté Application
   (`appli.<context>`).

### Ajouter une réaction

1. Créer le handler dans `src/Infrastructure/Messenger/Event/Handler/<Context>/`, `final readonly`,
   annoté `#[AsMessageHandler(bus: 'event.bus')]`.
2. Choisir le régime d'idempotence (§5) et injecter `DomainEventLedgerInterface` en conséquence.
   Utiliser `self::class` comme clé de handler.
3. Passer par le `CommandBusInterface` si la réaction est un cas d'usage métier.
4. Réserver `UnrecoverableMessageHandlingException` à un message réellement invalide ; les états
   métier attendus sont des no-op journalisés et marqués comme traités.
5. Écrire le test unitaire dans `tests/Infrastructure/Unit/Messenger/Event/Handler/` : chemin
   nominal, événement déjà traité, et no-op métier.

## 10. Diagnostic

```bash
bin/console debug:messenger event.bus     # handlers enregistrés, par événement
bin/console messenger:stats               # profondeur des files
bin/console messenger:failed:show --transport=failed_domain_events
```

Requêtes utiles :

```sql
-- Outbox en attente
SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'domain_events';

-- Messages en échec des Domain Events
SELECT id, created_at FROM messenger_messages WHERE queue_name = 'failed_domain_events' ORDER BY id DESC;

-- Messages en échec du formulaire de contact
SELECT id, created_at FROM messenger_messages WHERE queue_name = 'failed_async' ORDER BY id DESC;

-- Réactions déjà exécutées pour un événement
SELECT handler, processed_at FROM processed_domain_event WHERE event_id = '…';
```

Un outbox qui grossit sans se vider signifie que le worker est arrêté — c'est la première chose
à vérifier devant un Customer manquant ou un e-mail non parti.

## Limites connues

- **Ordre non garanti** entre messages distincts d'une même transaction. Aucune réaction actuelle
  n'en dépend, mais un futur enchaînement causal devrait l'expliciter plutôt que de compter sur
  l'ordre d'insertion.
- **Doublon d'e-mail possible** en cas de crash entre l'envoi et le marquage (§5).
- **Cache Shop non invalidé.** Les queries Shop sont cachées, mais `DomainEventCacheTags` ne
  connaît que les événements User : elles dépendent de leur seul TTL (§6).
- **`OrderPlacedEvent` / `OrderPaidEvent` ne sont pas publiés** tant que le use case Ordering
  n'est pas extrait du legacy.
