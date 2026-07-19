# Application Layer – Use Cases, CQRS & Ports

> **But** : orchestrer les cas d'usage, sans détails techniques ni logique métier.
> Couche `application/`. Règles transverses : voir `AGENTS.md` racine. Règles métier : voir `domain/AGENTS.md`.

---

## Rôle & dépendances

- Contient : Commands / Queries, Handlers, Ports (interfaces), services applicatifs partagés (Clock, Transaction, …).
- Peut dépendre de : **Domain** + **Ports** (`application/.../Port`).
- Ne doit **pas** dépendre de : Presentation, Infrastructure, Symfony / Doctrine / API Platform.

---

## Ports (interfaces)

Un service applicatif interne, pur et sans dépendance technique (par exemple `AuthTokenIssuer`), est injecté directement par sa classe : ne pas créer de Port ni d'adapter artificiel pour lui.

Un Port représente une dépendance externe ou technique que l'Application doit abstraire : repository, horloge, configuration, chiffrement, fournisseur de token, etc.

**Shared Ports** (`Application/Shared/Port/`) :
- `ClockInterface` (temps `now()`), `ConfigInterface` (config), `TransactionalInterface` (exécution atomique),
- `FileInterface` (fichier — pas d'`UploadedFile` Symfony), `EventDispatcherInterface` (events), `UuidGeneratorInterface` (UUID).

**Ports métier** (ex. `Application/User/Port/`) :
- `UserRepositoryInterface`, `PasswordHasherInterface`, `TokenProviderInterface`, `AvatarUploaderInterface`, etc.

> **Règle** : toute dépendance externe (DB, HTTP client, FS, queue…) → un Port dans `application/.../Port`, implémenté dans `infrastructure/...` (cf. `infrastructure/AGENTS.md`). Les services applicatifs purs internes restent des classes concrètes injectées directement.

---

## CQRS – Organisation

- `UseCase/Command/...` : `*Command` + `*CommandHandler`.
- `UseCase/Query/...` : `*Query` + `*QueryHandler`.
- Handler : une seule méthode publique `handle(SomethingCommand|SomethingQuery $message)`.
- Buses & resolvers dans `Application/Shared/CQRS/` — indépendants des frameworks (PSR-11, PSR-3, convention-based, avec cache).

### Contrat des Commands et Queries

- Une Command ou Query est un message de transport : elle ne porte que des primitives sérialisables et loggables (`string`, `int`, `bool`, tableaux de primitives, etc.), jamais de Value Object du Domain.
- Exposer par exemple `userId: string`, et non `userId: UserId`. Le message reste ainsi transportable sur un bus et indépendant du Domain.
- La conversion primitive → Value Object (`UserId::fromString($command->userId)`) se fait à l'entrée du use case, dans le handler. C'est le seul point où une entrée devient un concept métier valide.
- Ne pas construire de Value Object dans la Presentation pour l'injecter dans un message : cela déplacerait la validation Domain hors du use case et dégraderait la sérialisabilité du message.
- Cette règle est cohérente avec les DTO d'entrée Symfony/Messenger : ils portent des scalaires, puis le handler hydrate les Value Objects nécessaires au Domain.

### Buses — règle absolue

- Toujours passer par `CommandBusInterface` / `QueryBusInterface` — **jamais** appeler `handle()` directement.
- Mantra : **« toujours via le Bus, jamais via le Handler »**.
- **Aucun mapping manuel** Command → Handler : découverte automatique par convention.
  - `FooCommand` → `FooCommandHandler`, `BarQuery` → `BarQueryHandler`.
  - Les resolvers (`CommandHandlerResolver`, `QueryHandlerResolver`) appliquent la convention, résolvent via PSR-11 et mettent en cache les callables. Voir `docs/CQRS_handler_resolver.md`.

### Middlewares CQRS

- Vivent dans `Application/Shared/CQRS/Middleware/`.
- Rôles **cross-cutting uniquement** : logging (PSR-3), metrics, validation croisée — **pas de logique métier**.
- Ordre / activation câblés dans `config/services.yaml` (Infrastructure) via `!tagged_iterator`.

---

## Command Handlers

- Orchestrent l'écriture : charger des agrégats via repos, appeler les méthodes métier Domain, persister / publier les events via les Ports.
- Utilisent **uniquement** : Domain + Ports (`UserRepositoryInterface`, `ClockInterface`, `TransactionalInterface`, …) + services applicatifs purs internes injectés directement.
- Renvoient : DTOs d'output / read models, ou `void` — **jamais** d'entités Doctrine ni d'objets framework.

### Transactions — performance et cohérence

Les commandes d'écriture utilisent `TransactionalInterface`, avec des transactions **aussi courtes que possible**, sans sacrifier la cohérence des décisions prises sur l'état persistant.

- **Avant la transaction** : préparer tout ce qui est pur et indépendant de l'état persistant : validation de format, parsing d'identifiants, construction de Value Objects, calculs, génération locale de slug/UUID et validation de fichier. Une erreur à ce stade ne doit pas ouvrir de transaction.
- **Dans la transaction** : toute lecture de repository qui conditionne une écriture, puis les mutations et persistance associées. Cela couvre les contrôles d'existence, d'appartenance, de stock, de parenté, d'unicité applicative et les relectures nécessaires au résultat de la commande.
- **Hors transaction** : les lectures réellement indépendantes de l'écriture (par exemple un use case de lecture) et tout I/O externe ou potentiellement long (HTTP, stockage de fichier, queue). Ces effets doivent être coordonnés par un mécanisme adapté, pas maintenus sous verrou DB.

Les tests de commande reflètent ce découpage : une erreur de validation pure attend que `transactional()` ne soit pas appelé ; un échec issu d'une lecture DB décisionnelle attend l'exécution du callback transactionnel.

### Aucune logique métier dans l'Application

Calculs de montants/totaux, conversions d'unités monétaires (euros↔cents), arithmétique sur prix/quantités, décisions métier (ex. « quantité 0 ⇒ retirer la ligne ») appartiennent au **Domain** (VOs `Money`, méthodes d'agrégat). Cela vaut pour les handlers, les **services applicatifs** et les **factories de read model**.

> **Application = orchestration, Domain = règles métier.**
> Un handler charge l'agrégat, appelle une méthode métier (`Cart::changeLineQuantity()`, `Money::fromEuros()`, …) et persiste — il ne calcule ni ne décide lui-même.

- Calcul de prix → `Money::multiply()` / `Money::add()` / `Money::fromEuros()` / `toEuros()`, **jamais** `* 100`, `/ 100`, `round()` ni devise codée en dur.
- Règle de transition d'état → méthode d'agrégat dédiée, pas un `if` dans le handler.
- **Restent acceptables** dans le handler : les guards techniques (404 « not found », `null === $aggregate`) et les invariants *set-based* nécessitant un repository (limite par compte, unicité, réassignation d'adresse par défaut).

---

## Query Handlers

- Lecture seule (pas d'effets de bord).
- Utilisent : read models, repositories de lecture, ports dédiés.
- Renvoient : DTOs de lecture, collections typées.

---

## Gestion du temps (ClockInterface)

- Ne jamais faire `new \DateTimeImmutable()` dans Application.
- Toujours : injecter `ClockInterface`, utiliser `$this->clock->now()`, passer `$now` au Domain.

---

## Messenger (asynchrone)

- Messages (DTO immuables) dans `application/src/Shared/Messenger/Message`.
- Les **handlers** Messenger sont côté Infrastructure (`#[AsMessageHandler]`, routage `config/packages/messenger.yaml`) : pas de logique métier dedans, l'orchestration reste dans les use-cases Application.

---

## Testabilité Application

- Chaque handler dépend d'interfaces (Ports) → testable avec des mocks (`UserRepositoryInterface`, `ClockInterface`, …), sans kernel.
- **Aucun** attribut/annotation framework dans Application (`#[AsMessageHandler]`, `#[AutowireIterator]`, …) → wiring uniquement dans Infrastructure.
- **Test obligatoire par use case** : chaque `*Command`/`*Query` doit avoir sa classe `*Test` (ex. `AddToCartCommand` → `AddToCartTest`), dans `application/tests/Unit/<Contexte>/UseCase/Command|Query[/<sous-domaine>]/`. Vérifié **automatiquement** par `HandlerConventionTest` (suite `appli.shared`) : un handler livré sans test fait échouer GrumPHP (pre-commit) et la CI — ce n'est pas qu'une recommandation.
- Suites : `appli.usecase.user`, `appli.shop` (cf. `AGENTS.md` racine).

### Conventions de tests unitaires

- Les tests Application sont des tests unitaires PHPUnit purs : pas de kernel Symfony, pas de conteneur, pas de DB, pas d'implémentation Infrastructure.
- Namespace attendu : `App\Application\Tests\Unit\<Contexte>\...`, miroir du namespace `App\Application\<Contexte>\...`.
- Nom de classe : retirer le dossier de use case et remplacer le suffixe `Command` / `Query` par `Test`.
  - `App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin\CreateProductByAdminCommand`
  - → `App\Application\Tests\Unit\Shop\UseCase\Command\Catalog\CreateProductByAdminTest`
- Chaque classe de test est `final`, étend `PHPUnit\Framework\TestCase`, déclare `strict_types=1` et type ses mocks avec intersections `PortInterface&MockObject`.
- Les dépendances du handler/service sont créées dans `setUp()` avec `$this->createMock(...)`; le handler/service testé y est instancié directement.
- Les tests appellent `handle()` directement **uniquement dans la couche de tests Application** pour vérifier le handler isolé. Le code de production hors Application passe toujours par les Bus.
- Nom des méthodes : `testHandle...()` pour les use cases, `test<Method>...()` pour les services applicatifs, avec un comportement lisible (`testHandleThrowsWhenProductNotFound`, `testCreateSkipsLinesWhoseProductIsMissing`).
- Un test de handler couvre au minimum le chemin nominal, les erreurs métier/techniques attendues (`not found`, unicité, limite atteinte, token invalide, etc.) et les effets d'orchestration utiles.
- Les ports sont vérifiés explicitement avec `expects($this->once())`, `expects($this->never())`, `with(...)`, `willReturn(...)` ou `willThrowException(...)` pour documenter le contrat d'orchestration.
- Pour `TransactionalInterface`, le mock exécute le callback afin de tester le contenu de la transaction :

```php
$this->transactional->expects($this->once())
    ->method('transactional')
    ->willReturnCallback(static fn (callable $callback) => $callback());
```

- Les commandes d'écriture vérifient que les agrégats sont persistés via le repository (`save`, `delete`, etc.) et utilisent `$this->callback(...)` quand il faut inspecter l'état de l'objet sauvegardé.
- Les queries vérifient les paramètres transmis au repository de lecture (`filters`, `orderBy`, pagination) et l'output retourné. Les queries cacheables testent aussi `cacheKey()`, `cacheTtl()` et `cacheTags()`.
- Quand un cache key dépend de tableaux (`filters`, `orderBy`), ajouter un test de stabilité si l'ordre des clés ne doit pas changer la clé de cache.
- Utiliser des fixtures locales privées (`private function createUser()`, `createProduct()`, `createCategory()`, etc.) pour construire des objets Domain lisibles, sans fixture globale ni Object Mother prématurée.
- Privilégier des dates fixes (`new DateTimeImmutable('2025-01-01 10:00:00')`) et des UUID explicites pour rendre les assertions déterministes.
- Les tests peuvent utiliser des constantes privées pour les UUID répétitifs d'un scénario.
- Les assertions portent sur les objets métier / read models retournés (`assertSame`, `assertTrue($id->equals(...))`, `assertInstanceOf`, `assertCount`) plutôt que sur une sérialisation JSON ou un format HTTP.
- Pour un chemin d'erreur, vérifier aussi les interactions qui ne doivent pas arriver (`save`, `hash`, `generateRandomToken`, `resolve`, etc.) avec `expects($this->never())`.
- Les messages d'exception sont testés quand ils font partie du contrat actuel ou clarifient une règle métier.

---

## Checklist Application

- [ ] Le code est dans `application/.../UseCase/Command|Query`.
- [ ] Le DTO s'appelle `...Command` ou `...Query` ; le handler `...CommandHandler` / `...QueryHandler` et expose `handle()`.
- [ ] Les Commands et Queries ne portent que des primitives sérialisables ; le handler les convertit en Value Objects Domain à l'entrée du use case.
- [ ] Presentation/Infra n'appellent jamais `handle()` directement — uniquement via les Buses.
- [ ] Le handler dépend uniquement de Ports, Domain et services applicatifs purs internes injectés directement ; aucun Port/adaptateur artificiel n'est créé pour ces derniers.
- [ ] Le temps est géré via `ClockInterface`.
- [ ] **Aucune logique métier** : calculs de montants/totaux, conversions d'unités, arithmétique prix/quantités et décisions métier délégués au Domain (VOs/agrégats).
- [ ] Aucun attribut framework dans Application.
- [ ] Les tests mockent les Ports et tournent sans kernel.
- [ ] Chaque use case a une classe `*Test` (ex. `AddToCartCommand` → `AddToCartTest`), dans `application/tests/Unit/<Contexte>/UseCase/Command|Query[/<sous-domaine>]/`. **Vérifié automatiquement** par `HandlerConventionTest` (suite `appli.shared`) : un handler sans test fait échouer GrumPHP et la CI.
- [ ] Les tests couvrent le chemin nominal, les erreurs attendues, les interactions positives/négatives avec les Ports et les métadonnées de cache des queries concernées.
