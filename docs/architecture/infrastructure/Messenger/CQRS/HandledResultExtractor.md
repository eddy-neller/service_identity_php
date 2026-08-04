# HandledResultExtractor

Fichier documenté : `infrastructure/src/Messenger/CQRS/HandledResultExtractor.php`.

## Rôle

`HandledResultExtractor` extrait le résultat métier d'un `Envelope` Symfony
Messenger une fois qu'il a traversé un bus (`command.bus` ou `query.bus`).

Messenger ne renvoie jamais directement la valeur retournée par un handler :
`MessageBusInterface::dispatch()` renvoie un `Envelope`, et le résultat du
handler est attaché dessus sous forme de `HandledStamp`. Cette classe centralise
la lecture de ce stamp pour que les adaptateurs de bus n'aient pas à connaître ce
détail de fonctionnement de Messenger.

```php
public function extract(Envelope $envelope): mixed
{
    $handledStamps = $envelope->all(HandledStamp::class);
    $handledCount = count($handledStamps);

    if (1 !== $handledCount) {
        throw new LogicException(...);
    }

    return $handledStamps[0]->getResult();
}
```

## Garde : exactement un `HandledStamp`

Un Command ou une Query CQRS applicative doit toujours être traité par **un et un
seul** handler. `HandledResultExtractor` fait respecter ce contrat en comptant les
`HandledStamp` présents sur l'enveloppe :

- **0 stamp** : aucun handler ne correspond au message (convention `FooCommand` →
  `FooCommandHandler` rompue, ou message routé par erreur vers un transport asynchrone
  au lieu d'être traité en synchrone — cf.
  [`docs/CQRS_messenger.md`](../../../../CQRS_messenger.md)) ;
- **> 1 stamp** : plusieurs handlers sont enregistrés sur le même message, ce qui
  viole la convention CQRS un-message/un-handler.

Dans les deux cas, une `LogicException` explicite est levée immédiatement plutôt que
de laisser un résultat `null` ou arbitraire remonter silencieusement à l'appelant.

## Utilisation

Consommé par les deux adaptateurs de bus Application → Infrastructure :

- `MessengerCommandBus::dispatch()` — extrait le résultat après passage sur
  `command.bus` ;
- `MessengerQueryBus::dispatch()` — extrait le résultat après passage sur `query.bus`.

Également utilisé par `QueryCacheMiddleware` pour extraire le résultat au moment de
peupler le cache lors d'un cache *miss*. Sur un cache *hit*, le middleware ne rappelle
pas l'extracteur : il reconstruit lui-même un `HandledStamp` synthétique sur
l'enveloppe retournée, afin que l'appelant (`MessengerQueryBus`) retrouve toujours
exactement un stamp, que le résultat vienne du cache ou d'une exécution réelle du
handler.

## Limites

Cette classe ne connaît rien du contenu métier du résultat (`mixed`) : elle ne fait
que garantir la cardinalité du traitement et déléguer l'extraction à l'API Messenger.
Le typage du résultat retourné est de la responsabilité de l'appelant applicatif
(`CommandBusInterface::dispatch()` / `QueryBusInterface::dispatch()`).
