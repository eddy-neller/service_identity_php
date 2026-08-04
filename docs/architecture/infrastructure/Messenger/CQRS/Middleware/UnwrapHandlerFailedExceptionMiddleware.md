# UnwrapHandlerFailedExceptionMiddleware

Fichier documenté : `infrastructure/src/Messenger/CQRS/Middleware/UnwrapHandlerFailedExceptionMiddleware.php`.

## Rôle

`UnwrapHandlerFailedExceptionMiddleware` relance l'exception métier d'origine levée
par un handler, au lieu de laisser remonter le `HandlerFailedException` avec lequel
Symfony Messenger l'enveloppe systématiquement.

Quand un handler lève une exception (ex. `UserNotFoundException`,
`ValidationException` applicative), Messenger l'intercepte et la remballe dans un
`HandlerFailedException`, avec la liste des exceptions par handler dans
`getWrappedExceptions()`. Sans ce middleware, tout appelant (contrôleur API
Platform, mapping d'exceptions, tests) devrait connaître Messenger et déballer ce
wrapper lui-même pour retrouver le type réel de l'erreur — ce qui romprait le
contrat historique des Buses CQRS et les mappings d'exceptions déjà en place côté
Presentation.

```php
try {
    return $stack->next()->handle($envelope, $stack);
} catch (HandlerFailedException $exception) {
    $wrappedExceptions = $exception->getWrappedExceptions();

    if (1 === count($wrappedExceptions)) {
        throw array_values($wrappedExceptions)[0];
    }

    throw $exception;
}
```

## Pourquoi seulement le cas à une exception ?

Par convention CQRS du projet, un Command ou une Query est traité par **un et un
seul** handler (`FooCommand` → `FooCommandHandler`, cf.
[`docs/CQRS_messenger.md`](../../../../../CQRS_messenger.md)). Dans ce cas normal,
`getWrappedExceptions()` contient donc exactement une entrée : c'est elle qui est
relancée telle quelle, avec son type et son message d'origine intacts.

Si plusieurs exceptions sont wrappées, la convention un-message/un-handler est déjà
violée en amont (plusieurs handlers enregistrés sur le même message). Ce cas est
volontairement laissé tel quel : le `HandlerFailedException` d'origine est relancé
sans déballage, plutôt que de choisir arbitrairement laquelle des exceptions
remonter. Un tel cas doit être détecté et corrigé au niveau de l'enregistrement des
handlers, pas masqué ici.

## Position dans le pipeline

Placé juste après le middleware de logging et avant les middlewares métier
(`QueryCacheMiddleware` sur `query.bus`) et les middlewares par défaut de
Messenger :

```text
command.bus : logging → exception unwrapping → Messenger defaults → handler
query.bus   : logging → exception unwrapping → query cache → Messenger defaults → handler
```

Il doit intercepter l'exception après qu'elle ait été journalisée par
`CommandLoggingMiddleware` / `QueryLoggingMiddleware` (qui loggent avant de
relayer), et avant qu'elle ne quitte le bus, pour que tout code appelant
(`MessengerCommandBus`, `MessengerQueryBus`, et in fine Presentation) ne voie
jamais de `HandlerFailedException` dans le cas courant à un seul handler.

## Utilisation

Enregistré sur les deux bus CQRS dans `config/packages/messenger.yaml` :

```yaml
command.bus:
  middleware:
    - App\Infrastructure\Messenger\CQRS\Middleware\CommandLoggingMiddleware
    - App\Infrastructure\Messenger\CQRS\Middleware\UnwrapHandlerFailedExceptionMiddleware
query.bus:
  middleware:
    - App\Infrastructure\Messenger\CQRS\Middleware\QueryLoggingMiddleware
    - App\Infrastructure\Messenger\CQRS\Middleware\UnwrapHandlerFailedExceptionMiddleware
    - App\Infrastructure\Messenger\CQRS\Middleware\QueryCacheMiddleware
```

Il n'est volontairement pas branché sur `async.bus` : les messages techniques
asynchrones (ex. `SendEmailMessage`) ne suivent pas le contrat CQRS
Command/Query et n'ont pas besoin de ce déballage.

## Limites

Ce middleware ne transforme ni ne mappe l'exception : il se contente de retirer
l'enveloppe Messenger quand elle ne porte aucune information supplémentaire utile
(cas à un seul handler). Le mapping vers une réponse HTTP (codes d'erreur API
Platform, etc.) reste entièrement à la charge de la couche Presentation, à partir
de l'exception métier d'origine ainsi restaurée.
