# `config/packages/messenger.yaml` — service_identity

> Chaque option du fichier, et **pourquoi** elle a cette valeur ici. Le fichier de config reste la
> source de vérité ; ce document explique ce qu'un `git diff` ne dira jamais.
>
> Vue d'ensemble des bus et du découpage CQRS : [`../../CQRS_messenger.md`](../../CQRS_messenger.md).
> Cycle de vie des Domain Events et outbox : [`../../domain_events.md`](../../domain_events.md).

---

## `default_bus: command.bus`

Le bus utilisé quand un service type-hinte `MessageBusInterface` sans préciser lequel. Les trois
autres bus sont visés explicitement (`#[AsMessageHandler(bus: '…')]`, `#[Autowire(service: '…')]`).
Sans ce réglage, Symfony refuse de démarrer dès qu'il y a plus d'un bus.

## `buses`

Quatre bus, parce qu'ils n'ont ni la même responsabilité ni les mêmes middlewares.

### `command.bus` — écritures

| Middleware | Rôle |
|---|---|
| `CommandLoggingMiddleware` | journalise chaque commande (nom, durée, issue) |
| `UnwrapHandlerFailedExceptionMiddleware` | Messenger emballe toute exception du handler dans `HandlerFailedException`. La couche Presentation attend l'exception métier d'origine (pour la mapper en statut HTTP) : ce middleware la déballe |
| `CacheInvalidationMiddleware` | purge les tags de cache après le commit du handler |

**L'ordre est significatif** : un middleware entoure tous ceux qui le suivent. `CacheInvalidation`
est donc en dernier, au plus près du handler, pour ne purger qu'une fois la transaction acquise.

### `query.bus` — lectures

Même schéma, avec `QueryCacheMiddleware` en dernier : il sert une lecture depuis le cache, ou la
calcule puis la stocke.

### `event.bus` — réactions aux Domain Events

```yaml
default_middleware:
  allow_no_handlers: true
middleware:
  - doctrine_ping_connection
  - doctrine_close_connection
```

- **`allow_no_handlers: true`** : par défaut, dispatcher un message sans handler lève une exception.
  Ici, un fait métier sans réaction dédiée est normal — la moitié des événements ne sert qu'aux
  journaux. Sans cette option, publier un événement avant d'écrire ce qui l'exploitera ferait
  échouer le worker.
- **`doctrine_ping_connection`** : un worker vit des heures. Sa connexion PostgreSQL peut être
  fermée entre deux messages (timeout serveur), et le message suivant échouerait sur une connexion
  morte. Ce middleware la teste et la rouvre.
- **`doctrine_close_connection`** : ferme la connexion après le message, pour ne pas garder une
  transaction ou une connexion ouverte pendant l'attente.
- **Pas de `UnwrapHandlerFailedExceptionMiddleware`, contrairement aux deux autres bus** : Messenger
  s'appuie sur `HandlerFailedException` pour arbitrer retry et échec définitif. La déballer
  priverait la stratégie de retry de ce sur quoi elle décide.

### `async.bus` — messages techniques

`~` : aucun middleware ajouté, donc la pile par défaut de Messenger. Il porte `SendEmailMessage`
(formulaire de contact). Il n'a **pas** `UnwrapHandlerFailedException` pour la même raison que
`event.bus` : ses messages sont retentés, pas rendus à un client HTTP.

---

## `failure_transport: failed_domain_events`

Réglage **global** : cible par défaut de `messenger:failed:show` et `messenger:failed:retry` quand on
ne passe pas `--transport`. Chaque transport déclare pourtant la sienne, donc ce réglage ne
rattraperait qu'un transport ajouté sans `failure_transport` explicite.

Il pointait avant sur `failed_legacy` (file `failed`), supprimée : elle ne recevait plus rien et la
table ne contenait aucune ligne. Pour l'autre file : `--transport=failed_async`.

**Ce réglage est facultatif.** Sans lui *et* sans `failure_transport` par transport, un message qui a
épuisé ses tentatives est simplement supprimé, avec une ligne de log : rien ne le conserve, rien
n'est rejouable.

---

## `transports`

### `domain_events` — outbox transactionnel

```yaml
dsn: "doctrine://default?queue_name=domain_events&auto_setup=false"
```

- **`doctrine://`** et non `amqp://` : l'`INSERT` du message est émis sur la connexion Doctrine
  courante, donc **dans la même transaction** que la persistance de l'agrégat. Publier dans RabbitMQ
  serait une double écriture : l'événement pourrait partir alors que la transaction a échoué,
  ou l'inverse.
- **`queue_name`** : plusieurs transports partagent la table `messenger_messages` et ne se
  distinguent que par cette colonne.
- **`auto_setup=false`** : la table est créée par une migration Doctrine, pas par le transport.
  Laissé à `true`, le transport tenterait un `CREATE TABLE` au premier message — un `DDL` inattendu
  en production, et un besoin de droits que l'utilisateur applicatif ne devrait pas avoir.
- **LISTEN/NOTIFY (défaut, non désactivé ici)** : sur PostgreSQL, le producteur émet de toute façon
  un `SELECT pg_notify(...)` à chaque insert — c'est inconditionnel dans `Connection::executeInsert()`.
  L'option `use_notify` n'agit que sur le **consommateur**. À `false`, on paierait le `notify` sans
  que personne n'écoute, et les workers retomberaient en polling. À `true`, le coût producteur est
  identique et le réveil est immédiat.

### `retry_strategy`

```yaml
max_retries: 6 · delay: 10000 · multiplier: 2 · max_delay: 300000 · jitter: 0.1
```

Attente doublée à chaque échec, plafonnée : 10 s, 20 s, 40 s, 80 s, 160 s, 300 s — **environ 10
minutes** au total. C'est calibré pour garder une marge avant l'expiration d'un token de
réinitialisation de mot de passe. `jitter: 0.1` fait varier chaque délai de ±10 %, pour que plusieurs
workers ne réessaient pas à la même seconde après une panne commune.

Au-delà de `max_retries`, le message part dans le `failure_transport` du transport.

**Sauf pour une exception récupérable.** Messenger rejoue `RecoverableMessageHandlingException` sans
limite de nombre, au même rythme (le plafond de 5 min devient la cadence). C'est ce que lève
`PublishUserIntegrationEventHandler` quand le bus est injoignable : la publication attend le retour
du bus au lieu de finir dans la file d'échec ([`docs/integration_events.md`](../../integration_events.md)).
`max_retries` ne borne donc que les vrais échecs, ceux qu'un humain doit voir.

### `async` — messages techniques

```yaml
dsn: "%env(MESSENGER_TRANSPORT_DSN)%"   # amqp://…@rabbitmq:5672/%2f/messages
```

RabbitMQ, **interne à ce service** : ces messages n'ont pas besoin d'être transactionnels, et une
file de tâches est l'outil adapté. En environnement `test`, `.env.test` fixe cette variable à
`in-memory://` — ni la suite ni la CI ne démarrent de broker.

Retry plus long que pour les Domain Events : 30 s à 15 min, **environ 31 minutes**. Un SMTP
indisponible se rétablit souvent en dizaines de minutes, et aucun token n'expire entre-temps.

### `failed_domain_events` et `failed_async`

Deux files d'échec **distinctes**, dans la même table, ce qui permet des alertes, une rétention et
des reprises adaptées à chaque flux.

**`use_notify: false`** ici, et seulement ici : ces files sont consommées **à la main**
(`messenger:failed:show`, `messenger:failed:retry`), jamais par un worker en attente. Le `LISTEN`
n'aurait personne à réveiller.

**Un rejeu raté retourne dans sa file, et c'est `KeepFailedReplayInFailureTransportListener` qui le
garantit.** Laissé à lui-même, Messenger envoie un message rejoué depuis une file d'échec, s'il échoue
sans être réessayé, dans la file d'échec **de cette file** : la globale, `failed_domain_events`, faute
de réglage propre. Pour `failed_async`, le message changeait donc de file. Et pour
`failed_domain_events` elle-même, qui est sa propre file d'échec, Messenger renonce, puis le worker
supprime la ligne : le message est **perdu**. Déclarer une file comme sa propre `failure_transport`
ne corrige rien : c'est précisément le cas où Messenger renonce.

---

## `routing`

```yaml
App\Domain\SharedKernel\Event\DomainEventInterface: domain_events
App\Application\Shared\Messenger\Message\SendEmailMessage: async
```

Le routage cible une **interface** : tout nouveau Domain Event part dans l'outbox sans qu'une ligne
soit à ajouter ici. Un message non listé n'est pas routé du tout — il est traité en ligne, dans le
processus qui l'a dispatché.

---

## `when@test`

```yaml
domain_events: "sync://"
```

En test, les réactions s'exécutent **en ligne** : les tests API conservent le comportement
synchrone d'avant l'outbox, sans worker à lancer.

Ce réglage est propre à ce service : **ne pas le recopier dans `service_shop`**, dont les tests
d'intégration exercent le transport d'outbox lui-même (réservation, ack, redélivrance) — un
transport `sync` ne sait pas recevoir, et la suite tombe en erreur.

Le transport `async` n'a pas besoin d'y figurer : `.env.test` le neutralise déjà via la DSN.
