# CLAUDE.md

> Conventions et architecture du projet. Lire intégralement avant toute modification.

## Stack

- PHP 8.4 (`declare(strict_types=1);` obligatoire dans tout fichier PHP)
- Symfony 7.4, API Platform 4.2, Doctrine ORM
- Tests : PHPUnit + DAMA DoctrineTestBundle
- Qualité : PHPStan, PHP-CS-Fixer, Rector, PHPCS, PhpMD
- Ne pas introduire de dépendances imposant PHP < 8, Symfony < 7 ou API Platform < 4

## Commandes principales

```bash
make install              # Build images, containers, vendors, init DB dev+test
make up / make down       # Docker up/down

# Qualité
make stan                 # PHPStan
make phpcsfixer_dry       # PHP-CS-Fixer dry-run
make phpcsfixer_fix       # PHP-CS-Fixer auto-fix
make phpcs                # PHPCS
make phpmd                # PhpMD
make rector-dry           # Rector dry-run
make rector               # Rector auto-fix

# Tests
make unit                         # Suite complète
make unit-filter f=ClassNameTest  # Test ciblé
make unit-suite s=<suite>         # Suite ciblée (voir table ci-dessous)
make unit-coverage                # Coverage HTML dans coverage/
```

## Suites de tests par périmètre

Lancer la suite correspondante **avant chaque livraison** si le périmètre est touché.

| Périmètre modifié | Suite (`make unit-suite s=...`) |
|---|---|
| `application/**/User/UseCase` | `appli.usecase.user` |
| `domain/SharedKernel` | `domain.shared` |
| `domain/Shop` | `domain.shop` |
| `domain/User` | `domain.user` |
| `infrastructure/**/Command/User` | `infra.command.user` |
| `infrastructure/**/Notification/User` | `infra.notif.user` |
| `infrastructure/**/Persistence` | `infra.persist` |
| `infrastructure/**/Service/Encoder` | `infra.service.encoder` |
| `infrastructure/**/Service/Token` | `infra.service.token` |
| `infrastructure/**/Service/User` | `infra.service.user` |
| `presentation/**/State/SendMail` | `pres.state.sendmail` |
| `presentation/**/State/Shared` | `pres.state.shared` |
| `presentation/**/State/User` | `pres.state.user` |
| `tests/Api/Shop` | `api.shop` |
| `presentation/tests/Api/User` | `api.user` |

> Les suites API (`api.*`) ne sont pas exécutables dans l'environnement courant.
> Ne pas ajouter de tests dans les dossiers exclus de `phpunit.dist.xml` (`<exclude>`). Placer les nouveaux tests dans les suites existantes.

---

## Architecture (Clean Architecture + DDD)

```
Presentation  →  Application  →  Domain
                     ↓
                  Ports (interfaces)
                     ↑
              Infrastructure (adapters)
```

### Règles de dépendance strictes

- **Domain** : PHP pur uniquement. Aucune dépendance vers Application, Infrastructure, Presentation, ni framework (Symfony, Doctrine, API Platform, Ramsey).
- **Application** : dépend de Domain + Ports. Jamais de Presentation, Infrastructure ou framework (Symfony, Doctrine, API Platform).
- **Infrastructure** : implémente les Ports. Dépend de Domain + frameworks. Jamais de Presentation.
- **Presentation** : expose l'API. Communique avec Application **uniquement via les Buses CQRS**. Jamais d'accès direct aux repos/services Infrastructure ni aux implémentations concrètes des Ports.
  - **Exception** : `stateOptions: new Options(entityClass: ...)` dans les attributs `#[ApiResource]` peut référencer une entité Doctrine Infrastructure. C'est un couplage imposé par API Platform (ORM bridge) — acceptable uniquement pour ce paramètre `entityClass`.

### Structure des dossiers

```
domain/                  Cœur métier pur, par bounded context :
  ├── User/src/            Model/, ValueObject/, Event/, Exception/
  ├── Shop/src/            idem
  └── SharedKernel/src/    DomainEventInterface, DomainEventTrait, …

application/             Cas d'usage & orchestration :
  ├── src/Shared/          CQRS/ (Buses, Resolvers, Middleware), Port/ (ClockInterface, etc.)
  └── src/<Context>/       UseCase/Command/, UseCase/Query/, Port/

infrastructure/          Adapters & implémentations :
  └── src/                 Persistence/, Service/, Notification/, Command/, …

presentation/            Interface HTTP/API :
  ├── src/<Context>/       ApiResource/, Dto/, State/, Presenter/, Security/, Validator/
  └── src/Shared/          Adapter/, State/ (providers/processors génériques)

src/                     Legacy (ne pas étendre)
```

---

## Conventions de code

- PSR-12 + conventions Symfony (4 espaces, 1 classe/fichier, types de retour explicites)
- Classes/interfaces : `PascalCase` — Méthodes/propriétés : `camelCase` — Constantes : `UPPER_SNAKE_CASE` — Clés d'env/config : `SNAKE_CASE`
- Services `final` et `readonly` (handlers, providers, processors, adapters)
- `mixed` uniquement aux frontières (`ProcessorInterface`, `ProviderInterface`)
- Imports `use` explicites (pas de FQCN inline). Après un move/rename, vérifier et ajuster les `use` en haut de fichier
- Utiliser les attributs PHP natifs (Doctrine mapping, API Platform Metadata, listeners Symfony)

---

## Domain Layer

### Entités & Agrégats

- Constructeur privé/protégé. Création via factory methods : `create()`, `register()`, `place()`, `reconstitute()`
- Pas de `setXxx()` public. Modifications via méthodes métier (`activate`, `cancel`, `changeEmail`...)
- Chaque méthode métier gère : invariants, `updatedAt`, Domain Events
- Le Domain est l'unique source de vérité pour la génération d'ID (VOs)

### Value Objects

- `final`, propriétés `private` (souvent `readonly`), immuables
- Validation métier dans le constructeur / factory (`fromString`, `fromInt`, …)
- Comparaison par valeur : `equals(self $other): bool`
- Utiliser des VOs pour emails, montants, quantités, statuts, tokens, etc. — pas de `string`/`int` bruts

### Temps & timestamps

- Jamais `new \DateTimeImmutable()` dans Domain — recevoir `DateTimeImmutable $now` en paramètre
- `createdAt` : immuable, défini dans les factory methods (pas de `setCreatedAt()`)
- `updatedAt` : mis à jour dans chaque méthode métier via l'appel `$this->touch($now);` (jamais d'assignation directe ni de `setUpdatedAt()`)

```php
private function touch(\DateTimeImmutable $now): void
{
    $this->updatedAt = $now;
}
```

### Domain Events

- Vivent dans `domain/<Context>/src/Event/`, implémentent `DomainEventInterface` du SharedKernel
- L'Aggregate Root enregistre (`recordEvent()`) et expose (`releaseEvents()`) les events

### Exceptions métier

- Base par bounded context (`UserDomainException`, `OrderDomainException`, …)
- Messages métier, pas techniques
- Nouvelles exceptions exposées à l'API → ajouter le mapping HTTP dans `config/packages/api_platform.yaml` (`exception_to_status`)

### Tests Domain

- Tests unitaires purs : pas de kernel Symfony, pas de DB, pas de services framework
- Pattern : créer VOs/Agrégats → appeler méthodes métier → vérifier état, events, exceptions

---

## Application Layer (CQRS)

### Organisation

- `UseCase/Command/...` : `*Command` + `*CommandHandler`
- `UseCase/Query/...` : `*Query` + `*QueryHandler`
- Handler : une seule méthode publique `handle()`
- Buses & resolvers dans `Application/Shared/CQRS/` (PSR-11, PSR-3, convention-based, avec cache)

### Buses — règle absolue

- Toujours passer par `CommandBusInterface` / `QueryBusInterface` — **jamais** appeler `handle()` directement
- Mantra : **"toujours via le Bus, jamais via le Handler"**
- Aucun mapping manuel Command → Handler (découverte automatique par convention : `FooCommand` → `FooCommandHandler`)

### Command Handlers

- Orchestrent l'écriture : charger agrégats via repos, appeler méthodes métier Domain, persister / publier events via Ports
- Utilisent uniquement : Domain + Ports (`UserRepositoryInterface`, `ClockInterface`, `TransactionalInterface`, …)
- Renvoient : DTOs d'output / read models, ou `void` — **jamais** d'entités Doctrine ou objets framework

### Query Handlers

- Lecture seule (pas d'effets de bord)
- Utilisent : read models, repositories de lecture, ports dédiés
- Renvoient : DTOs de lecture, collections typées

### Ports (interfaces)

Toute dépendance externe → Port dans `application/.../Port`, implémenté dans `infrastructure/`.

**Shared Ports** (`Application/Shared/Port/`) :
- `ClockInterface` (temps), `ConfigInterface` (config), `TransactionalInterface` (transactions atomiques)
- `FileInterface` (fichiers), `EventDispatcherInterface` (events), `UuidGeneratorInterface` (UUID)

**Ports métier** (ex. `Application/User/Port/`) :
- `UserRepositoryInterface`, `PasswordHasherInterface`, `TokenProviderInterface`, `AvatarUploaderInterface`, etc.

### Middlewares CQRS

- Vivent dans `Application/Shared/CQRS/Middleware/`
- Rôles : logging (PSR-3), metrics, validation croisée — uniquement cross-cutting, pas de logique métier
- Ordre/activation câblés dans `services.yaml` via `!tagged_iterator`

### Temps (ClockInterface)

- Jamais `new \DateTimeImmutable()` dans Application
- Toujours : injecter `ClockInterface`, utiliser `$this->clock->now()`, passer `$now` au Domain

### Testabilité Application

- Chaque handler dépend d'interfaces (Ports) → testable avec des mocks
- Aucun attribut/annotation framework dans Application (`#[AsMessageHandler]`, `#[AutowireIterator]`, etc.)
- Wiring → uniquement dans Infrastructure (`config/services.yaml`)

---

## Infrastructure Layer

### Rôle

Implémenter **tous les Ports** Application et encapsuler les frameworks.

### Ports → Implémentations

| Port (Application) | Implémentation (Infrastructure) |
|---|---|
| `ClockInterface` | `SystemClock` |
| `ConfigInterface` | `ParameterBagConfig` |
| `TransactionalInterface` | `DoctrineTransactional` |
| `FileInterface` | `SymfonyFileAdapter` |
| `EventDispatcherInterface` | `SymfonyEventDispatcherAdapter` |
| `UuidGeneratorInterface` | `RamseyUuidGenerator` |
| `UserRepositoryInterface` | `DoctrineUserRepository` |
| `PasswordHasherInterface` | `SymfonyPasswordHasherAdapter` |
| `TokenProviderInterface` | `TokenProvider` |
| `AvatarUploaderInterface` | `AvatarUploader` |

Tous les bindings dans `config/services.yaml`.

### Mapping Domain ↔ Persistence

- Entités Doctrine ≠ entités Domain
- Mappers dédiés : `UserMapper::toDomain(DoctrineUser): DomainUser`, `UserMapper::toDoctrine(DomainUser, ?DoctrineUser): DoctrineUser`
- Le mapper utilise `DomainUser::reconstitute()` pour reconstruire l'agrégat sans events
- Préserver les timestamps Domain dans le mapping

### Index & contraintes Doctrine

- Déclarer les index et contraintes uniques **dans l'entité Doctrine** via attributs `#[ORM\Index]` / `#[ORM\UniqueConstraint]` (source de vérité), jamais uniquement dans la migration
- Nommage explicite et obligatoire, en PascalCase :
  - Index : `{Entité}{Colonnes}Idx` (ex. `ShopCustomerUserAccountIdx`, `ShopCartLineCartIdx`)
  - Contraintes uniques : `{Entité}{Colonnes}Uniq` (ex. `ShopCartCustomerUniq`, `ShopCartLineProductUniq`)
- Les index implicites des clés étrangères (`ManyToOne` / `OneToOne`) doivent être **déclarés explicitement** pour figer leur nom — sinon Doctrine génère un nom hashé (`IDX_…`/`UNIQ_…`) et `doctrine:schema:update` produit un diff de renommage permanent
- La migration doit utiliser **exactement** les mêmes noms que les attributs d'entité
- Exceptions restant uniquement dans la migration (pas d'attribut Doctrine équivalent) : `CHECK` constraints et index fonctionnels (GIN/`trgm`, expressions)
- Vérifier l'absence de diff avec `php bin/console doctrine:schema:update --dump-sql` après toute modification d'index

### Dépendances

- Infrastructure peut dépendre de : Ports Application, Domain, frameworks
- Infrastructure ne doit **jamais** dépendre de `Presentation`

### Temps & `DateTimeImmutable`

- `new \DateTimeImmutable()` est interdit en `Domain` et en `Application`
- En `Application`, toujours injecter `ClockInterface` et utiliser `$this->clock->now()`
- En `Domain`, toujours recevoir `DateTimeImmutable $now` en paramètre des méthodes métier et factory methods
- En `Infrastructure`, `new \DateTimeImmutable()` est autorisé dans `SystemClock`, les entités Doctrine, les event subscribers Symfony, les console commands et les lifecycle callbacks Doctrine

---

## Presentation Layer

### Flux typique

**Écriture** (POST/PUT/PATCH/DELETE) :
```
HTTP Request → Input DTO (validation) → Processor → Command → CommandBus → Handler → Domain/Ports → Output → Resource
```

**Lecture** (GET/collection) :
```
HTTP Request → Provider → Query → QueryBus → Handler → Read model → Presenter → Resource
```

### Providers / Processors (State)

- Valider le type de `$data` (ou la présence des `$uriVariables`) et lever `LogicException(PresentationErrorCode::INVALID_INPUT->value)` si incohérent
- Construire un `...Command` / `...Query` et dispatcher via les Buses
- Convertir les outputs Domain en ressources via un Presenter (ex. `UserResourcePresenter`)
- Presentation ne crée ni n'injecte de Handlers directement

### Validation & Sécurité

- Validation dans les DTOs Presentation (`Assert\*`, validators custom) — côté HTTP uniquement, pas de logique métier
- Sécurité : `security` / `security_post_denormalize` sur les opérations API Platform
- Endpoints `/me` : utiliser `UserMeSecurityTrait` (garantit 401/403 correct, entry point JWT) — ne pas lever d'exception HTTP directe
- Rôles centralisés via `RoleSet` (`RoleSet::ROLE_ADMIN`) dans les expressions `security`

### Sérialisation & groupes

- Convention `snake_case` basés sur `shortName` : `user:read`, `send_mail:write`
- Admin-only : groupe `{shortName}:admin` (ajouté dynamiquement par `AdminGroup` si `ROLE_ADMIN`)
- Ne pas créer de groupes ad-hoc non liés au `shortName`/opération

### Adapters

- Objets framework adaptés à la frontière : `SymfonyFileAdapter` → `FileInterface`
- Application ne voit que l'interface, jamais `UploadedFile` Symfony
- Ne pas faire transiter `UploadedFile` dans Application/Domain

### Dépendances autorisées

- `CommandBusInterface`, `QueryBusInterface`, DTOs Application
- Domain pour certains VOs (ex. `UserId`) ou modèles Domain dans les Presenters
- Symfony (validation, sécurité, sérialisation), API Platform

### Dépendances interdites

- Repositories Doctrine, services `infrastructure/*`, implémentations concrètes des Ports

---

## API Platform

- `shortName` sur `#[ApiResource]`, `name` stable sur chaque `Operation`
- UUID : `App\Presentation\RouteRequirements::UUID` pour les paramètres `{id}`
- **Variable d'URI ≠ identifiant de la ressource** : si une opération a un `uriTemplate` dont la variable ne correspond pas à l'identifiant de la ressource (ex. `/cart/items/{productId}` sur `ShopCart` identifié par `id`), API Platform génère par défaut une `uriVariable` nommée `id` → `InvalidIdentifierException` → 404 « Invalid uri variables ». Déclarer alors explicitement la variable : `uriVariables: ['productId' => new Link(fromClass: SelfResource::class, identifiers: ['productId'])]`. La propriété (`productId`) n'existant pas sur la ressource, aucune transformation n'est tentée et la valeur brute arrive dans le Processor/Provider. Ajouter aussi `read: false` quand l'opération n'a ni `provider` ni `stateOptions` (la cible est résolue par le Processor, ex. via le client courant) pour éviter une lecture par défaut vouée à l'échec.
- Endpoints sécurisés : `security` + OpenAPI `security: [['ApiKeyAuth' => []]]`
- Pagination : `PaginatedCollectionProvider` → attributs Request `_total_items` / `_total_pages` → `PaginationHeaderListener` produit `X-Total-Count` / `X-Total-Pages`. Ne pas recalculer/poser manuellement ces headers
- Pas d'endpoints hors API Platform si `ApiResource` + `Provider/Processor` suffit

### Uploads & fichiers (multipart)

- Déclarer `inputFormats: ['multipart' => ['multipart/form-data']]` et documenter le `RequestBody` OpenAPI (`format: binary`)
- Désérialisation via `MultipartDecoder` + `UploadedFileDenormalizer`
- URLs fichiers : s'appuyer sur la couche de normalisation / upload en place avec Vich, sans calcul d'URL à la main dans les ressources
- Adapter `File|UploadedFile` → `FileInterface` via `SymfonyFileAdapter` avant d'appeler Application

---

## Messenger (asynchrone)

- Messages (DTO immuables) dans `application/src/Shared/Messenger/Message`
- Handlers Messenger côté Infrastructure (`#[AsMessageHandler]`), routage via `config/packages/messenger.yaml`
- Pas de logique métier dans les handlers Messenger : orchestration métier dans Application, le handler Messenger ne fait que l'adaptation/IO

---

## Performance & observabilité

- Collections toujours paginées
- Éviter N+1 (joins, fetch modes, DTO read model)
- Cache (HTTP / Symfony Cache) si pertinent
- Logs structurés et corrélables (request ID si possible)

---

## Git & commits

- Branches : `main` (stable), `feat/*`, `fix/*`, `chore/*`
- Sujet impératif, ≤ 70 chars : "Add ...", "Fix ...", "Refactor ..."
- Body pour : contexte, breaking changes, décisions d'architecture
- Ne jamais committer de secrets (`.env.local*`, `makefile.conf`, secrets CI). `.env.test` = valeurs par défaut test uniquement
- Si ports/services Docker changent : mettre à jour **à la fois** `makefile.conf` ET `docker-compose*.yml`

---

## Checklist avant livraison

### Architecture

- [ ] Aucun `use App\Application\*`, `App\Infrastructure\*`, `App\Presentation\*` dans Domain
- [ ] Aucun import Symfony/Doctrine/API Platform/Ramsey dans Domain ni Application
- [ ] Presentation communique uniquement via les Buses (pas d'injection de Handlers)
- [ ] Infrastructure ne dépend pas de Presentation
- [ ] Chaque Port Application a son implémentation dans Infrastructure avec binding dans `services.yaml`

### Domain

- [ ] Agrégats créés via factory methods (`create`, `register`, `place`, `reconstitute`)
- [ ] Value Objects immuables avec validation d'invariants
- [ ] Pas de `setXxx()` public sur les agrégats
- [ ] Méthodes métier reçoivent `DateTimeImmutable $now`
- [ ] `createdAt` immuable, `updatedAt` mis à jour via `$this->touch($now);` (méthode privée `touch()`)
- [ ] Domain Events pour les changements importants

### Application

- [ ] Use case = Command/Query + Handler dans `UseCase/Command|Query`
- [ ] Handler dépend uniquement de Ports + Domain
- [ ] Temps géré via `ClockInterface`
- [ ] Aucun attribut framework dans Application

### Presentation

- [ ] Input HTTP → DTO → Command/Query (pas de Domain direct dans les endpoints)
- [ ] Output → Presenter → Resource API
- [ ] Validation et sécurité gérées ici, pas dans Application/Infra

### Qualité & tests

- [ ] `make stan` passe
- [ ] `make phpcsfixer_dry` passe (sinon `make phpcsfixer_fix`)
- [ ] Suite(s) de tests concernée(s) passent (`make unit-suite s=...`)
- [ ] `declare(strict_types=1);` dans tout nouveau fichier PHP
- [ ] Mapping Domain ↔ Doctrine via mappers dédiés (pas de raccourci)
