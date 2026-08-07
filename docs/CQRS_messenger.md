# CQRS synchrone avec Symfony Messenger

## Architecture

Les couches externes continuent à dépendre uniquement des contrats Application :

```text
Presentation / Infrastructure subscriber
        ↓
CommandBusInterface / QueryBusInterface          Application
        ↓
MessengerCommandBus / MessengerQueryBus          Infrastructure
        ↓
command.bus / query.bus                          Symfony Messenger
        ↓
*CommandHandler::handle() / *QueryHandler::handle()  Application
```

Application ne dépend pas de Symfony. Les adapters, l'extraction du résultat et les middlewares Messenger sont dans Infrastructure.

## Bus et synchronisme

Quatre bus sont configurés :

- `command.bus`, bus par défaut, pour les Commands ;
- `query.bus` pour les Queries.
- `async.bus` pour les messages techniques non sensibles, notamment `SendEmailMessage` du formulaire de contact, routé vers le transport `async`.
- `event.bus` pour les Domain Events, routés vers l'outbox Doctrine `domain_events`.

Les Commands et Queries ne sont routées vers aucun transport. Messenger appelle donc immédiatement leur handler dans le processus courant. Les adapters exigent exactement un `HandledStamp` et retournent son résultat ; une commande `void` produit un résultat `null` valide.

Un message CQRS routé accidentellement vers un transport ne produit aucun résultat immédiat et est rejeté par l'adapter. L'asynchronisme doit utiliser un message technique ou un événement dédié, pas modifier silencieusement le contrat CQRS.

### Mailer

`config/packages/mailer.yaml` déclare `message_bus: false` : l'asynchronisme est porté explicitement
par les messages techniques routés vers `async`, jamais implicitement par le Mailer Symfony. Les
e-mails d'activation et de réinitialisation sont déjà exécutés par le worker `domain_events` ; ils
sont donc remis au SMTP directement afin que leur lien à token ne soit jamais sérialisé dans
RabbitMQ ou `failed_domain_events`.

Les pannes externes ont des fenêtres de retry distinctes : `async` (formulaire de contact) retente
6 fois avec un backoff de 30 s à 15 min, soit environ 31 minutes au total ; `domain_events` retente
6 fois de 10 s à 5 min, soit environ 10 minutes, afin de conserver une marge avant l'expiration d'un
token de réinitialisation. Un jitter de 10 % évite que plusieurs workers réessaient simultanément.

Les erreurs définitives sont séparées : `async` alimente `failed_async` et `domain_events` alimente
`failed_domain_events`. Les deux sont des files Doctrine distinctes, ce qui permet des alertes, une
rétention et des reprises adaptées. `failed_legacy` ne reçoit plus rien ; il reste disponible pour
vider l'ancienne file commune lors du déploiement avec
`messenger:failed:show --transport=failed_legacy` puis `messenger:failed:retry --transport=failed_legacy`.

### Intégrité des messages asynchrones

Tous les handlers de Domain Events et `SendEmailMessageHandler` portent `sign: true`. Messenger signe
alors le corps sérialisé par HMAC SHA-256, avec `kernel.secret` (donc `APP_SECRET`), et stocke les
en-têtes `Body-Sign` et `Sign-Algo`. Un corps sans signature ou altéré est rejeté avant d'atteindre
le handler. Cela protège l'intégrité, **pas** la confidentialité : aucun secret ne doit être ajouté
aux messages pour autant.

La mise en service requiert de drainer `async`, `domain_events` et leurs failure transports avant le
déploiement : les messages créés avant cette évolution n'ont pas de signature et seront refusés. Tous
les producteurs et workers doivent conserver le même `APP_SECRET` pendant ce drainage ; une rotation
de cette clé impose le même drainage préalable.

## Enregistrement des handlers

Les handlers conservent leur méthode `handle()` et implémentent `CommandHandlerInterface` ou `QueryHandlerInterface`. `_instanceof` ajoute automatiquement le tag Messenger avec le bus et la méthode appropriés :

```yaml
services:
    _instanceof:
        App\Application\Shared\CQRS\Command\CommandHandlerInterface:
            tags:
                - { name: messenger.message_handler, bus: command.bus, method: handle }
        App\Application\Shared\CQRS\Query\QueryHandlerInterface:
            tags:
                - { name: messenger.message_handler, bus: query.bus, method: handle }
```

La convention reste obligatoire : `FooCommand` correspond à `FooCommandHandler`, et `BarQuery` à `BarQueryHandler`. `HandlerConventionTest` vérifie le nom, l'interface, la méthode, son argument typé et la présence du test de use case.

### Pourquoi `handle()` plutôt que `__invoke()`

Le tag `method: handle` est explicite alors que Messenger route par défaut vers `__invoke()`. Choix assumé, pas un oubli :

- Les tests Application appellent `handle()` directement pour vérifier un handler isolé (`$handler->handle($command)`), hors du Bus — `handle()` est lisible et grep-able en test, alors que la syntaxe invoke (`$handler($command)`) est plus opaque et moins bien supportée par le « find usages » d'un IDE sur une méthode magique.
- `handle()` documente l'intention CQRS sur l'interface elle-même, indépendamment du fait que Messenger l'appelle ou non ; `__invoke()` est un mécanisme PHP générique sans rapport avec le vocabulaire métier.
- Le passage à `__invoke()` ne supprimerait aucune configuration : le tag `_instanceof` reste nécessaire pour distinguer `bus: command.bus` de `bus: query.bus`.

## Middlewares

L'ordre utile des middlewares est :

```text
command.bus : logging → exception unwrapping → Messenger defaults → handler
query.bus   : logging → exception unwrapping → query cache → Messenger defaults → handler
```

Messenger enveloppe une exception de handler dans `HandlerFailedException`. Le middleware d'unwrapping relance l'unique exception originale afin de préserver les mappings API Platform et le contrat historique des buses.

Le cache des queries stocke uniquement le résultat. Sur un cache hit, le middleware ajoute un `HandledStamp` synthétique pour que l'adapter conserve exactement le même contrat que lors d'une exécution réelle du handler.

## Transactions et événements

Les Command handlers gardent leurs transactions courtes et explicites via `TransactionalInterface`. Aucun `DoctrineTransactionMiddleware` global n'est installé.

Les Domain Events sont publiés **dans** cette transaction, via `DomainEventBusInterface::publishAll()` appelé juste après la persistance de l'agrégat. `MessengerDomainEventBus` dispatche sur `event.bus`, qui route vers le transport `domain_events` : l'INSERT dans `messenger_messages` emprunte la connexion Doctrine courante, donc la transaction ouverte par le handler. Agrégat et événements sont commités ensemble, ou pas du tout.

Le dispatch s'arrête au `SendMessageMiddleware` — aucune réaction ne s'exécute pendant la requête HTTP. Les réactions sont exécutées par le worker `messenger:consume domain_events` (`make messenger-consume-events`).

Les workers Supervisor ont des limites de durée, mémoire, volume et échecs ; elles provoquent un
redémarrage après le message en cours. Utiliser `make messenger-stop-workers` au déploiement afin de
renouveler proprement les processus après publication du code.

Contrairement aux buses CQRS, `event.bus` porte `allow_no_handlers: true` et **aucun** `UnwrapHandlerFailedExceptionMiddleware` : Messenger s'appuie sur `HandlerFailedException` pour arbitrer retry / échec définitif, et un événement sans réaction dédiée ne doit pas faire échouer le worker.

> Cycle de vie complet des Domain Events — outbox, worker, idempotence, sérialisation, recettes d'ajout : [`domain_events.md`](domain_events.md).

## Diagnostic

```bash
php bin/console debug:messenger command.bus
php bin/console debug:messenger query.bus
php bin/console debug:messenger async.bus
php bin/console debug:messenger event.bus
php bin/console messenger:stats
php bin/console messenger:failed:show --transport=failed_async
php bin/console messenger:failed:show --transport=failed_domain_events
```

Chaque Command et Query applicative doit apparaître exactement une fois sur son bus, avec `method=handle`. `SendEmailMessage` doit rester uniquement sur `async.bus`. Sur `event.bus`, `LogDomainEventHandler` apparaît sur `DomainEventInterface` — il couvre donc tout événement, présent ou futur.
