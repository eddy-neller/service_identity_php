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

Trois bus sont configurés :

- `command.bus`, bus par défaut, pour les Commands ;
- `query.bus` pour les Queries.
- `async.bus` pour les messages techniques existants, notamment `SendEmailMessage` routé vers le transport `async`.

Les Commands et Queries ne sont routées vers aucun transport. Messenger appelle donc immédiatement leur handler dans le processus courant. Les adapters exigent exactement un `HandledStamp` et retournent son résultat ; une commande `void` produit un résultat `null` valide.

Un message CQRS routé accidentellement vers un transport ne produit aucun résultat immédiat et est rejeté par l'adapter. L'asynchronisme doit utiliser un message technique ou un événement dédié, pas modifier silencieusement le contrat CQRS.

### Mailer et `default_bus`

`config/packages/mailer.yaml` déclare `message_bus: async.bus`. Sans ce réglage, le Mailer Symfony dispatcherait son message interne d'envoi via `default_bus` (`command.bus`), qui porte `CommandLoggingMiddleware` et `UnwrapHandlerFailedExceptionMiddleware` : middlewares prévus pour les Commands applicatives, pas pour un message technique du framework. `async.bus` (`~`, sans middleware) évite ce mélange et garde l'envoi de mail purement infrastructurel.

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

Les Command handlers gardent leurs transactions courtes et explicites via `TransactionalInterface`. Aucun `DoctrineTransactionMiddleware` global n'est installé. Les Domain Events restent publiés après le retour réussi de cette transaction selon les règles Application actuelles.

Le middleware Messenger `dispatch_after_current_bus` reste disponible par défaut, mais ne remplace ni la transaction explicite ni une future outbox.

## Diagnostic

```bash
php bin/console debug:messenger command.bus
php bin/console debug:messenger query.bus
php bin/console debug:messenger async.bus
```

Chaque Command et Query applicative doit apparaître exactement une fois sur son bus, avec `method=handle`. `SendEmailMessage` doit rester uniquement sur `async.bus`.
