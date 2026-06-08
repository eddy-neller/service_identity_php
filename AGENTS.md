# AGENTS.md

> Guide pour humains **et** agents (Copilot/Cursor/LLM) : conventions, architecture, workflow, règles de contribution.
> Objectif : garder un code **lisible**, **testable**, **orienté métier**, et une API **robuste**.

---

## Stack & versions

* **PHP**: 8.4 (`declare(strict_types=1);` partout)
* **Symfony**: 7.4
* **API Platform**: 4.2
* **Doctrine ORM** + Migrations
* **Tests**: PHPUnit + DAMA DoctrineTestBundle
* **Qualité** : PHPStan, PHP-CS-Fixer, Rector, PhpMD

---

## Global Guidelines

### Stack & versions

**Do**

-   Cibler PHP `8.4.*`, Symfony `7.3.*`, API Platform `4.2.*` (cf. `composer.json`).
-   Utiliser les attributs PHP (Doctrine mapping, API Platform Metadata, listeners/decorators Symfony).

**Don't**

-   Introduire des dépendances qui imposent PHP `< 8`, Symfony `< 7` ou API Platform `< 4`.

---

### Conventions PHP (générales)

**Do**

-   Ajouter `declare(strict_types=1);` dans tout nouveau fichier PHP.
-   Préférer des services `final` et `readonly` (handlers, providers, processors, adapters).
-   Garder des types explicites (propriétés/retours) ; `mixed` uniquement aux frontières (ex. `ProcessorInterface`, `ProviderInterface`).

**Don't**

-   Introduire des propriétés dynamiques ou des tableaux “fourre-tout” non typés sans justification claire.

---

### API Platform (ressources & opérations)

**Do**

-   Définir `shortName` au niveau `#[ApiResource]` et un `name` stable sur chaque `Operation` (utile pour les groupes auto et l’OpenAPI).
-   Utiliser `App\Presentation\RouteRequirements::UUID` pour les paramètres `{id}` UUID.
-   Quand la variable d'`uriTemplate` **ne correspond pas à l'identifiant** de la ressource (ex. `/cart/items/{productId}` sur `ShopCart` identifié par `id`), déclarer explicitement `uriVariables: ['productId' => new Link(fromClass: SelfResource::class, identifiers: ['productId'])]`. Sinon API Platform génère une `uriVariable` nommée `id` et lève `InvalidIdentifierException` → **404 « Invalid uri variables »**. La propriété pointée n'existe pas sur la ressource : la valeur brute (string) arrive telle quelle dans le Processor/Provider.
-   Ajouter `read: false` sur ces opérations lorsqu'elles n'ont ni `provider` ni `stateOptions` (la cible étant résolue par le Processor, ex. via le client courant), pour ne pas déclencher une lecture par défaut qui échouerait.
-   Pour les endpoints sécurisés, déclarer `security` et compléter l’OpenAPI avec `security: [['ApiKeyAuth' => []]]`.
-   Pour les collections paginées, utiliser `App\Presentation\Shared\State\PaginatedCollectionProvider` afin d’exposer `X-Total-Count` / `X-Total-Pages`.

**Don't**

-   Ajouter des endpoints “hors API Platform” si une `ApiResource` + `Provider/Processor` suffit.

---

### Providers / Processors (State)

**Do**

-   Valider le type du `$data` (ou la présence des `$uriVariables`) et lever `LogicException(App\Presentation\Shared\State\PresentationErrorCode::INVALID_INPUT->value)` si incohérent.
-   Construire un `...Command` / `...Query` et dispatcher via `CommandBusInterface` / `QueryBusInterface` (pas d’appel direct à `handle()`).
-   Convertir les outputs Domain en ressources exposées via un Presenter (ex. `App\Presentation\User\Presenter\UserResourcePresenter`).

**Don't**

-   Mettre de la logique de rendu (mapping/formatage) dans un handler Application ; faire la transformation côté Presentation (Presenter).

---

### Sérialisation & groupes

**Do**

-   Respecter la convention `snake_case` des groupes basés sur `shortName` (ex. `send_mail:write`, `user:read`).
-   Pour les champs “admin-only”, utiliser le groupe `{shortName}:admin` (ajouté dynamiquement par `App\Infrastructure\Serializer\ContextBuilder\AdminGroup` si l’utilisateur a `ROLE_ADMIN`).

**Don't**

-   Créer des groupes ad-hoc non liés au `shortName`/opération (difficiles à maintenir et à déboguer).

---

### Pagination (headers)

**Do**

-   Laisser `App\Infrastructure\EventListener\PaginationHeaderListener` produire `X-Total-Count` / `X-Total-Pages` via les attributs Request `_total_items` / `_total_pages` (posés par `PaginatedCollectionProvider`).

**Don't**

-   Recalculer ou poser manuellement ces headers dans un Processor/Provider.

---

### Uploads & fichiers (multipart)

**Do**

-   Déclarer `inputFormats: ['multipart' => ['multipart/form-data']]` et documenter le `RequestBody` OpenAPI (champ `format: binary`).
-   Adapter `File|UploadedFile` (Symfony) en `FileInterface` via `App\Presentation\Shared\Adapter\SymfonyFileAdapter` avant d’appeler l’Application.
-   S’appuyer sur `App\Infrastructure\Service\Encoder\MultipartDecoder` et `App\Infrastructure\Serializer\Denormalizer\UploadedFileDenormalizer` pour la désérialisation multipart.
-   Pour exposer les URLs de fichiers, s’appuyer sur la couche de normalisation / upload en place avec Vich (pas de calcul d’URL à la main dans les ressources).

**Don't**

-   Faire transiter `UploadedFile` dans Application/Domain (adapter à la frontière).

---

### Messenger (asynchrone)

**Do**

-   Déclarer les messages (DTO immuables) dans `application/src/Shared/Messenger/Message`.
-   Implémenter les handlers Messenger côté Infrastructure (`#[AsMessageHandler]`) et router les messages via `config/packages/messenger.yaml`.

**Don't**

-   Mettre de la logique métier dans un handler Messenger : garder l’orchestration métier dans les use-cases (Application), le handler Messenger ne fait que l’adaptation/IO.

---

### Sécurité (JWT, /me, voters)

**Do**

-   Pour les endpoints `/me`, utiliser `App\Presentation\User\Security\UserMeSecurityTrait` afin de garantir le comportement 401/403 attendu (entry point JWT).
-   Centraliser les rôles via `App\Domain\User\Security\ValueObject\RoleSet` (ex. `RoleSet::ROLE_ADMIN`) dans les expressions `security`.

**Don't**

-   Lever une exception HTTP “directe” pour `/me` quand on attend une authentification (préférer l’exception Security utilisée dans le trait).

---

### Project Structure

**Domain-driven layout :**

-   `domain/` – cœur métier :
    -   entités / agrégats (`Model/`),
    -   value objects (`ValueObject/`),
    -   domain events (`Event/`),
    -   exceptions métier (`Exception/`).
-   `application/` – cas d’usage & orchestration :
    -   CQRS (Commands/Queries + Handlers),
    -   Ports (interfaces vers l’extérieur),
    -   services applicatifs partagés.
-   `infrastructure/` – implémentations techniques :
    -   Doctrine (repositories, mappers),
    -   adapters Symfony / HTTP / FS / queue,
    -   implémentations des Ports Application.
-   `presentation/` – interface HTTP/API :
    -   ressources API Platform,
    -   DTOs HTTP,
    -   Processors / Providers,
    -   Presenters, validators, sécurité.

**HTTP/UI :**

-   `public/`, `templates/`, `translations/`, `resources/` pour :
    -   assets,
    -   templates éventuels,
    -   fichiers de traduction.

**Tests & tooling :**

-   `migrations/` :
    -   migrations Doctrine.
-   Docker & Make :
    -   `docker*/`, `docker-compose*.yml`,
    -   `Makefile`, `makefile.conf(.dist)`.

**Infra :**
-   `config/` - configuration Symfony.
-   `docker/` – config Docker.

**Legacy :**

-   `src/` :
    – code legacy.
-   `tests/` :
    - tests legacy.

---

### Build and Development Commands

Utiliser **`make`** pour éviter les lignes de commande trop longues (Docker = runtime par défaut) :

```bash
make install        # build images, containers, vendors, init DB dev+test
make up / down      # docker-compose up/down; down-hard pour prune images/volumes

make serve-start    # Symfony local server si non Docker
make serve-stop
```

---

### Coding Style & Naming

-   PSR-12 + conventions Symfony via PHPCS / PHP-CS-Fixer :

    -   indentation 4 espaces,
    -   1 classe par fichier,
    -   types de retour explicites.

-   Naming :

    -   Classes / interfaces : `PascalCase`

        -   ex. `RegisterUserCommandHandler`, `DisplayUserQueryHandler`, `UserRepositoryInterface`.

    -   Méthodes / Propriétés / paramètres : `camelCase`.
    -   Constantes : `UPPER_SNAKE_CASE`
    -   Clés d’env / config : `SNAKE_CASE`.

```bash
make stan           # PHPStan
make phpcsfixer_dry # PHP-CS-Fixer en dry-run
make phpcsfixer_fix # PHP-CS-Fixer
make phpcs          # PHPCS
make phpmd          # PHPMD
make rector-dry     # refacto assistée en dry-run
make rector         # refacto assistée
```

---

### Testing Guidelines

-   Config PHPUnit : `phpunit.dist.xml`.
-   Utiliser :

```bash
make unit                       # full PHPUnit suite
make unit-filter f=ClassNameTest   # test ciblé
make unit-suite s=api.catalog      # suite ciblée
make unit-coverage             # HTML coverage dans coverage/
```

-   Base de données :

    -   DB de test dédiée, initialisée par `make install`,
    -   ne **jamais** réutiliser la DB de dev pour les tests.

Suites déclarées dans `phpunit.dist.xml` (pour `make unit-suite s=...`) :

-   `appli.usecase.user` → `application/tests/Unit/User/UseCase` (cas d’usage Application/User)
-   `domain.shared` → `domain/SharedKernel/tests/Unit` (logique métier SharedKernel)
-   `domain.shop` → `domain/Shop/tests/Unit` (logique métier Shop)
-   `domain.user` → `domain/User/tests/Unit` (logique métier User)
-   `infra.command.user` → `infrastructure/tests/Unit/Command/User` (commandes Symfony côté Infra)
-   `infra.notif.user` → `infrastructure/tests/Unit/Notification/User` (adapters de notification User)
-   `infra.persist` → `infrastructure/tests/Unit/Persistence` (persistence/Doctrine)
-   `infra.service.encoder` → `infrastructure/tests/Unit/Service/Encoder` (encodeurs/hashing)
-   `infra.service.token` → `infrastructure/tests/Unit/Service/Token` (génération/validation tokens)
-   `infra.service.user` → `infrastructure/tests/Unit/Service/User` (services User Infra)
-   `pres.state.sendmail` → `presentation/tests/Unit/State/SendMail` (processors/providers SendMail)
-   `pres.state.shared` → `presentation/tests/Unit/State/Shared` (state génériques Presentation)
-   `pres.state.user` → `presentation/tests/Unit/State/User` (state User côté Presentation)
-   `api.shop` → `tests/Api/Shop` (tests API Platform Shop)
-   `api.user` → `presentation/tests/Api/User` (tests API Platform User)
-   `unit.command` → `tests/Unit/Command` (commandes Symfony côté Legacy)
-   `unit.repository` → `tests/Unit/Repository` (repositories custom)

**Règle d’exécution :** toute modification impactant un périmètre couvert par l’une de ces suites doit déclencher systématiquement le test correspondant (`make unit-suite s=...`) avant livraison. Exception actuelle : les suites API (`presentation/tests/Api/*`) ne peuvent pas encore être lancées dans l’environnement courant.

---

### Imports PHP

-   Quand un fichier change de namespace ou de dossier, **ajoute/ajuste les imports `use`** plutôt que d’utiliser des classes pleinement qualifiées dans le code (évite les `new \App\...` en plein corps).
-   Vérifie le haut de fichier après un move/rename pour conserver la lisibilité (`use App\Domain\User\Identity\ValueObject\Username;` plutôt que `\App\Domain\User\Identity\ValueObject\Username` inline).
-   **Ne pas ajouter de tests dans les dossiers exclus de `phpunit.dist.xml`** (`<exclude>`). Place les nouveaux tests dans les suites existantes (cf. sections `<testsuite>`).

---

### Git & PR workflow

**Branching :**

* `main` : stable
* `feat/*` : features
* `fix/*` : corrections
* `chore/*` : maintenance/outillage

**Commits :**

-   Style impératif : “Add …”, “Fix …”, “Refactor …”
-   Sujet court, impératif (≤ 70 chars) :

    -   ex. `Add CQRS handler for user registration`.

-   Body pour :

    -   contexte,
    -   breaking changes,
    -   décisions d’architecture.

---

### Security & Configuration

-   Ne jamais committer de secrets :

    -   utiliser `.env.local*`, `makefile.conf`, secrets CI.
    -   `.env.test` = valeurs par défaut spécifiques aux tests.

-   Quand les ports / services Docker changent :

    -   mettre à jour **à la fois** :

        -   `makefile.conf`
        -   `docker-compose*.yml`

    -   pour garder les environnements alignés (local, CI, prod).

---

### Performance & observabilité

* Collections toujours paginées
* Éviter N+1 (joins / fetch modes / DTO read model)
* Cache (HTTP / Symfony Cache) si pertinent
* Logs structurés et corrélables (request id si possible)

---

### Docs attendues

* `README.md` : quickstart, env, commandes, architecture courte
* `docs/` : conventions globales

---

## 2. Clean Architecture – Vue d’ensemble

### 2.1. Dépendances autorisées

```text
Presentation  →  Application  →  Domain
                    ↓
                 Ports (interfaces)
                    ↑
             Infrastructure (adapters)
```

**Règles d’or :**

-   `domain/` :

    -   ✅ logique métier pure (entities, VOs, events, exceptions),
    -   ❌ aucune dépendance vers Application / Infra / Presentation,
    -   ❌ aucun framework (Symfony, Doctrine, API Platform, Ramsey, …).

-   `application/` :

    -   ✅ dépend de Domain + Ports,
    -   ❌ ne dépend pas de Presentation / Infrastructure,
    -   ❌ n’utilise pas directement Symfony/Doctrine/API Platform.

-   `infrastructure/` :

    -   ✅ implémente les Ports,
    -   ✅ dépend de Domain + frameworks,
    -   ❌ ne dépend pas de Presentation.

-   `presentation/` :

    -   ✅ expose l’API (API Platform, contrôleurs, DTOs HTTP),
    -   ✅ parle à Application **via les Buses CQRS** + DTOs (Commands/Queries/Outputs),
    -   ❌ ne parle jamais directement aux handlers ou repos Infra.

---

## 3. Domain Layer – DDD & Règles Métier

> **But** : cœur métier pur, sans aucun détail technique.

### 3.1. Périmètre

La couche Domain contient :

-   Entités / Agrégats (`Model/`),
-   Value Objects (`ValueObject/`),
-   Domain Events (`Event/`),
-   Exceptions métier (`Exception/`).

Organisation par bounded context :

```text
domain/
├── User/
│   ├── src/Model/
│   ├── src/ValueObject/
│   ├── src/Event/
│   └── src/Exception/
├── Shop/
└── SharedKernel/
    └── src/Event/   # DomainEventInterface, DomainEventTrait, …
```

### 3.2. Règles clés

-   Domain utilise uniquement :

    -   PHP natif, SPL (`DateTimeImmutable`, exceptions standard, etc.),
    -   éventuellement `SharedKernel` (events).

-   Domain **ne dépend jamais** de :

    -   `App\Application\*`,
    -   `App\Infrastructure\*`,
    -   `App\Presentation\*`,
    -   Symfony, Doctrine, API Platform, Ramsey, HTTP.

-   Le Domain est l’unique source de vérité pour la génération d’ID (création via factory methods et VOs), l’Application ne fait que fournir les UUID via les Ports.

### 3.3. Entités & Agrégats

-   Aggregate Root :

    -   encapsule l’état métier,
    -   expose des **méthodes métier** (pas de `setXxx()` publics),
    -   ne contient pas de code technique.

-   Constructeur :

    -   privé ou protégé,
    -   création via factory methods : `create()`, `register()`, `place()`, `reconstitute()`.

-   Modifs d’état :

    -   toujours via méthodes métier (`activate`, `cancel`, `changeEmail`, `addItem`, …),
    -   qui gèrent :

        -   invariants,
        -   `updatedAt` (via `$this->touch($now);`),
        -   Domain Events.

### 3.4. Value Objects

-   `final`, propriétés `private` (souvent `readonly`).
-   Aucune mutation → immuables.
-   Validation métier dans le constructeur / factory (`fromString`, `fromInt`, …).
-   Comparaison par valeur avec `equals(self $other): bool`.
-   Utilisation :

    -   emails, montants, quantités, statuts, préférences, langues, tokens, limites, etc.
    -   ne pas laisser passer des `string`/`int` bruts pour ces concepts.

### 3.5. Domain Events

-   Représentent des faits métier :

    -   `OrderPlaced`, `OrderCancelled`, `UserRegistered`, …

-   Règles :

    -   vivent dans `domain/<Context>/src/Event/`,
    -   implémentent `DomainEventInterface` du SharedKernel,
    -   peuvent utiliser `DomainEventTrait` pour `occurredOn`.

-   L’Aggregate Root :

    -   enregistre les events (`recordEvent()`),
    -   les expose (`releaseEvents()`).

### 3.6. Exceptions métier

-   Base par bounded context :

    -   `UserDomainException`, `OrderDomainException`, …

-   Exceptions ciblées :

    -   `ActivationLimitReachedException`, `InsufficientStockException`, etc.

-   Messages métier, pas techniques.
-   Quand une nouvelle exception Domain peut remonter jusqu'a l'API, ajouter son mapping HTTP dans `api/config/packages/api_platform.yaml` (`api_platform.exception_to_status`).

### 3.7. Temps & timestamps

-   Domain ne fait **jamais** `new \DateTimeImmutable()` en dur.
-   Les méthodes métier reçoivent toujours `DateTimeImmutable $now`.
-   `createdAt` :

    -   défini dans les factory methods,
    -   **immuable** (pas de `setCreatedAt()`).

-   `updatedAt` :

    -   mis à jour dans chaque méthode métier qui modifie l’état,
    -   **toujours** via l’appel `$this->touch($now);` (jamais d’assignation directe ni de `setUpdatedAt()`).

```php
private function touch(\DateTimeImmutable $now): void
{
    $this->updatedAt = $now;
}
```

### 3.8. Testabilité Domain

-   Tests unitaires purs :

    -   pas de kernel,
    -   pas de DB,
    -   pas de services Symfony.

-   On :

    -   crée des VOs/Aggregates,
    -   appelle les méthodes métier,
    -   vérifie l’état, les events, les exceptions.

### 3.9. Checklist Domain

Avant de valider du code Domain :

-   [ ] Aucun `use App\Application\*`, `App\Infrastructure\*`, `App\Presentation\*`.
-   [ ] Aucun import Symfony/Doctrine/API Platform/HTTP/Ramsey.
-   [ ] Les agrégats sont créés via des factory methods (`create`, `register`, `place`, `reconstitute`).
-   [ ] Les Value Objects sont immuables et valident leurs invariants.
-   [ ] Toute méthode métier sensible reçoit un `DateTimeImmutable $now`.
-   [ ] `createdAt` immuable, `updatedAt` mis à jour via `$this->touch($now);` (méthode privée `touch()`).
-   [ ] Aucun `setXxx()` public sur les agrégats.
-   [ ] Les Domain Events existent pour les changements importants.
-   [ ] Les tests Domain tournent sans framework.

---

## 4. Application Layer – Use Cases & Ports

> **But** : orchestrer les cas d’usage, sans détails techniques.

### 4.1. Rôle & dépendances

-   Contient :

    -   Commands / Queries,
    -   Handlers,
    -   Ports (interfaces),
    -   services applicatifs partagés (Clock, Transaction, etc.).

-   Peut dépendre de :

    -   Domain,
    -   Ports (`application/.../Port`).

-   Ne doit pas dépendre de :

    -   Presentation,
    -   Infrastructure,
    -   Symfony / Doctrine / API Platform.

### 4.2. Ports (interfaces)

**Shared Ports (`Application/Shared/Port/`) :**

-   `ClockInterface` – abstraction du temps (`now()`).
-   `ConfigInterface` – lecture de configuration.
-   `TransactionalInterface` – exécution atomique de blocs.
-   `FileInterface` – abstraction de fichier (pas d’UploadedFile Symfony).
-   `EventDispatcherInterface` – publication d’événements.
-   `UuidGeneratorInterface` – génération d’UUID.

**Ports métiers (ex. User) (`Application/User/Port/`) :**

-   `UserRepositoryInterface`
-   `PasswordHasherInterface`
-   `TokenProviderInterface`
-   `AvatarUploaderInterface`
-   etc.

**Règle :**

> Toute dépendance externe (DB, HTTP client, FS, queue…)
> → un Port dans `application/.../Port`, implémenté dans `infrastructure/...`.

### 4.3. CQRS en Application

Organisation :

-   `UseCase/Command/...` :

    -   `*Command`,
    -   `*CommandHandler`.

-   `UseCase/Query/...` :

    -   `*Query`,
    -   `*QueryHandler`.

Conventions :

-   `SomethingCommand` → `SomethingCommandHandler`.
-   `SomethingQuery` → `SomethingQueryHandler`.
-   Handler :

    -   une seule méthode publique `handle(SomethingCommand|SomethingQuery $message)`.

Les buses & resolvers sont dans `Application/Shared/CQRS/` :

-   indépendants des frameworks (PSR-11, PSR-3).
-   `CommandHandlerResolver` :

    -   déduit le handler par convention (`FooCommand` → `FooCommandHandler`),
    -   utilise un container PSR-11,
    -   met en cache les callables.

-   `QueryHandlerResolver` :

    -   déduit le handler par convention (`FooQuery` → `FooQueryHandler`),
    -   utilise un container PSR-11,
    -   met en cache les callables.

### 4.4. Handlers – Règles

**Command Handlers :**

-   Orchestration d’écriture :

    -   charger des agrégats via les repositories,
    -   appeler les méthodes métier Domain,
    -   persister / publier les events via les Ports.

-   Utilisent uniquement :

    -   Domain,
    -   Ports (`UserRepositoryInterface`, `ClockInterface`, etc.),
    -   `TransactionalInterface` pour les transactions.

-   Ne renvoient que :

    -   DTOs d’output / read models,
    -   ou `void`.

-   **Jamais** :

    -   d’entités Doctrine,
    -   d’objets framework.

**Query Handlers :**

-   Lecture seule (pas d’effets de bord),
-   Utilisent :

    -   read models,
    -   repositories de lecture,
    -   ports dédiés.

-   Renvoient :

    -   DTOs de lecture,
    -   collections typées.

### 4.5. Gestion du temps (ClockInterface)

-   Ne jamais faire `new \DateTimeImmutable()` dans Application.
-   Toujours :

    -   injecter `ClockInterface`,
    -   utiliser `$this->clock->now()`,
    -   passer `$now` au Domain.

### 4.6. Testabilité Application

-   Chaque handler :

    -   dépend d’interfaces (Ports),
    -   est testable avec des mocks `UserRepositoryInterface`, `ClockInterface`, etc.

-   Aucun attribute/annotation framework dans Application :

    -   pas de `#[AsMessageHandler]`, `#[AutowireIterator]`, etc.

-   Wiring → uniquement dans Infrastructure.

### 4.7. Checklist Application

Avant d’ajouter/modifier un use case :

-   [ ] Le code est dans `application/.../UseCase/Command|Query`.
-   [ ] Le DTO s’appelle `...Command` ou `...Query`.
-   [ ] Le handler s’appelle `...CommandHandler` ou `...QueryHandler` et expose `handle()`.
-   [ ] Le handler dépend uniquement de Ports + Domain.
-   [ ] Le temps est géré via `ClockInterface`.
-   [ ] Les tests mockent les Ports et tournent sans kernel.

---

## 5. CQRS – Règles Globales (Application + Presentation)

### 5.1. Buses

-   Tout cas d’usage passe par :

    -   `CommandBusInterface` pour les écritures,
    -   `QueryBusInterface` pour les lectures.

-   Interdit :

    -   Presentation ne doit jamais injecter directement un handler,
    -   aucun code hors Application ne doit appeler `handle()`.

> Mantra : **“toujours via le Bus, jamais via le Handler”**.

### 5.2. Découverte automatique

-   Conventions :

    -   `FooCommand` → `FooCommandHandler`,
    -   `BarQuery` → `BarQueryHandler`.

-   Les resolvers (`CommandHandlerResolver` et `QueryHandlerResolver`) :

    -   appliquent ces conventions,
    -   résolvent via PSR-11,
    -   mettent en cache.

Aucun mapping manuel Command → Handler ailleurs.

### 5.3. Middlewares CQRS

-   Middlewares dans `Application/Shared/CQRS/Middleware/`.
-   Rôles :

    -   logging (PSR-3),
    -   metrics,
    -   validation croisée, etc.

-   Pas de logique métier, uniquement cross-cutting.
-   Ordre / activation câblés dans `services.yaml` (Infrastructure) via `!tagged_iterator`.

### 5.4. Checklist CQRS

-   [ ] Nouveau use case → Command/Query + Handler, pas de contrôleur “gros”.
-   [ ] Presentation utilise **uniquement** les Buses.
-   [ ] Aucun handler accessible directement depuis Presentation/Infra.
-   [ ] Eventuels tests pour vérifier que chaque Command/Query a un Handler associé (recommandé).

---

## 6. Infrastructure Layer – Adapters & Frameworks

> **But** : implémenter les Ports, encapsuler les frameworks.

### 6.1. Rôle

-   Implémenter **tous les Ports** Application :

    -   repos, hashers, file storage, email, queues, etc.

-   Encapsuler :

    -   Doctrine (ORM, migrations),
    -   Symfony (services, events, console),
    -   Vich (upload),
    -   Ramsey (UUID),
    -   HTTP clients, queues, FS, etc.

-   Exposer au reste :

    -   `SystemClock` (ClockInterface),
    -   `DoctrineTransactional` (TransactionalInterface),
    -   `SymfonyEventDispatcherAdapter` (EventDispatcherInterface),
    -   etc.

### 6.2. Dépendances

Infrastructure peut dépendre de :

-   `App\Application\...Port\...Interface` (Ports seulement),
-   `App\Domain\...` (agrégats, VOs, events),
-   frameworks & libs externes.

Infrastructure ne doit pas dépendre de :

-   `App\Presentation\*`.

### 6.3. Ports → Implémentations

Exemples :

-   `ClockInterface` → `SystemClock`
-   `ConfigInterface` → `ParameterBagConfig`
-   `TransactionalInterface` → `DoctrineTransactional`
-   `FileInterface` → `SymfonyFileAdapter`
-   `EventDispatcherInterface` → `SymfonyEventDispatcherAdapter`
-   `UuidGeneratorInterface` → `RamseyUuidGenerator`

Ports métier :

-   `UserRepositoryInterface` → `DoctrineUserRepository`
-   `PasswordHasherInterface` → `SymfonyPasswordHasherAdapter`
-   `TokenProviderInterface` → `TokenProvider`
-   `AvatarUploaderInterface` → `AvatarUploader`

**Règle :**

> Interface dans `application/…/Port`
> Implémentation + dépendances framework dans `infrastructure/...`
> Binding dans `config/services.yaml`.

### 6.4. Mapping Domain ↔ Persistence

-   Entités Doctrine ≠ entités Domain.
-   Utiliser des mappers dédiés :

    -   `UserMapper::toDomain(DoctrineUser $entity): DomainUser`,
    -   `UserMapper::toDoctrine(DomainUser $user, ?DoctrineUser $entity): DoctrineUser`.

-   Le mapper :

    -   consomme des VOs Domain,
    -   appelle `DomainUser::reconstitute()` pour reconstruire l’agrégat sans events,
    -   préserve les timestamps Domain.

### 6.5. Index & contraintes Doctrine

**Do**

-   Déclarer les index et contraintes uniques **dans l’entité Doctrine** via les attributs `#[ORM\Index]` / `#[ORM\UniqueConstraint]` (source de vérité), pas seulement dans la migration.
-   Nommer explicitement en PascalCase :
    -   index → `{Entité}{Colonnes}Idx` (ex. `ShopCustomerUserAccountIdx`, `ShopCartLineCartIdx`),
    -   contraintes uniques → `{Entité}{Colonnes}Uniq` (ex. `ShopCartCustomerUniq`, `ShopCartLineProductUniq`).
-   Déclarer **explicitement** les index implicites des clés étrangères (`ManyToOne` / `OneToOne`) pour figer leur nom.
-   Garder des noms **identiques** entre les attributs d’entité et la migration.
-   Vérifier l’absence de diff via `php bin/console doctrine:schema:update --dump-sql` après toute modification d’index.

**Don't**

-   Laisser Doctrine générer un nom hashé (`IDX_…` / `UNIQ_…`) : cela crée un diff de renommage permanent au `schema:update`.
-   Mettre un index récupérable en attribut uniquement dans la migration. Exceptions tolérées (sans équivalent attribut) : `CHECK` constraints et index fonctionnels (GIN/`trgm`, expressions).

### 6.6. Gestion du temps

-   `SystemClock` implémente `ClockInterface` :

```yaml
# config/services.yaml
services:
    App\Application\Shared\Port\ClockInterface:
        alias: App\Infrastructure\Service\SystemClock
```

-   `new \DateTimeImmutable()` est **autorisé** dans Infrastructure (c'est la seule couche où l'horloge réelle est instanciée) :
    -   `SystemClock::now()` → `new \DateTimeImmutable()`
    -   Entités Doctrine (lifecycle callbacks, `prePersist`, constructeurs d'entités)
    -   Event subscribers, console commands
-   Domain et Application **ne doivent jamais** l'instancier directement — ils reçoivent `$now` en paramètre (Domain) ou injectent `ClockInterface` (Application).

### 6.7. Checklist Infrastructure

-   [ ] Chaque Port Application a une implémentation claire.
-   [ ] Les implémentations vivent dans `infrastructure/...`, pas ailleurs.
-   [ ] Le mapping Domain ↔ Doctrine est géré par des mappers dédiés.
-   [ ] Aucun code Infra ne dépend de `presentation/`.
-   [ ] Tous les bindings Ports → Implémentations sont dans `services.yaml`.
-   [ ] Index/contraintes déclarés dans l'entité (`#[ORM\Index]`/`#[ORM\UniqueConstraint]`), nommés `…Idx`/`…Uniq`, identiques à la migration, sans diff `schema:update`.

---

## 7. Presentation Layer – API / HTTP

> **But** : exposer l’API, valider, sécuriser et transformer les données.

### 7.1. Rôle & dépendances

-   Gère :

    -   ressources API Platform,
    -   DTOs d’entrée (Input),
    -   Processors / Providers,
    -   Presenters, validators, sécurité.

-   Peut dépendre de :

    -   `CommandBusInterface`, `QueryBusInterface`,
    -   DTOs Application (Commands/Queries/Outputs),
    -   Domain pour quelques VOs (ex. `UserId`) ou modèles Domain dans les Presenters,
    -   Symfony (validation, sécurité, sérialisation),
    -   API Platform.

Ne doit pas dépendre de :

-   Repositories Doctrine,
-   Services `infrastructure/*` (hashers, FS, etc.),
-   Implémentations concrètes des Ports.

### 7.2. Flux typique

**Écriture :**

```text
HTTP Request
   ↓
Input DTO (Presentation)
   ↓
Processor
   ↓
Command (Application)
   ↓
CommandBusInterface
   ↓
Handler (Application)
   ↓
Domain / Ports
   ↓
Output/void
   ↓
(éventuelle transformation → Resource/API)
```

**Lecture :**

```text
HTTP Request
   ↓
Provider
   ↓
Query (Application)
   ↓
QueryBusInterface
   ↓
Handler (Application)
   ↓
Read model / Domain
   ↓
Presenter
   ↓
Resource/API
```

### 7.3. Structure recommandée

```text
Presentation/
├── User/
│   ├── ApiResource/   # Endpoints API Platform
│   ├── Dto/           # DTOs d'entrée (Input + validation)
│   ├── State/         # Processors / Providers (CQRS côté API)
│   ├── Presenter/     # Domain/Output → Resource
│   ├── Security/      # Traits & helpers de sécurité
│   └── Validator/     # Validateurs personnalisés Symfony
└── Shared/
    ├── Adapter/       # SymfonyFileAdapter → FileInterface
    └── State/         # Providers/Processors génériques
```

### 7.4. CQRS côté Presentation

-   **Processors** (POST/PUT/PATCH/DELETE) :

    -   Input DTO → Command,
    -   `CommandBusInterface` → Output/void.

-   **Providers** (GET/collection) :

    -   Query → `QueryBusInterface` → Output/read model,
    -   Presenter → Resource exposée.

Presentation ne crée ni n’injecte de Handlers.

### 7.5. Validation & Sécurité

-   Validation :

    -   dans les DTOs Presentation (`Assert\*`, validators custom),
    -   côté HTTP uniquement (pas de logique métier).

-   Sécurité :

    -   conditions `security` / `security_post_denormalize` API Platform,
    -   `Security` Symfony dans les Processors/Providers si besoin,
    -   traits réutilisables (`UserMeSecurityTrait`, etc.).

### 7.6. Adapters

-   Objets framework (ex. `UploadedFile`) sont adaptés à la frontière :

    -   `SymfonyFileAdapter` → `FileInterface`.

-   Application ne voit que l’interface `FileInterface`.

### 7.7. Checklist Presentation

-   [ ] Aucune dépendance vers les repos / services d’`infrastructure/`.
-   [ ] Communication avec Application uniquement via `CommandBusInterface` / `QueryBusInterface`.
-   [ ] Input HTTP → Input DTO → Command/Query – pas de Domain direct dans les endpoints.
-   [ ] Output Application/Domain → Presenter → Resource API.
-   [ ] Validation & sécurité gérées ici, pas dans Application/Infra.
