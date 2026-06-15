# Infrastructure Layer – Adapters & Frameworks

> **But** : implémenter les Ports Application et encapsuler les frameworks.
> Couche `infrastructure/`. Règles transverses : voir `AGENTS.md` racine.

---

## Rôle

- Implémenter **tous les Ports** Application : repos, hashers, file storage, email, queues, etc.
- Encapsuler : Doctrine (ORM, migrations), Symfony (services, events, console), Vich (upload), Ramsey (UUID), HTTP clients, queues, FS, etc.
- Héberger les **handlers Messenger** (`#[AsMessageHandler]`) : adaptation/IO uniquement, l'orchestration métier reste en Application.

---

## Dépendances

- Infrastructure peut dépendre de : `App\Application\...Port\...Interface` (Ports seulement), `App\Domain\...` (agrégats, VOs, events), frameworks & libs externes.
- Infrastructure ne doit **jamais** dépendre de `App\Presentation\*`.

---

## Ports → Implémentations

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

> **Règle** : interface dans `application/…/Port`, implémentation + dépendances framework dans `infrastructure/...`, **binding dans `config/services.yaml`**.

---

## Mapping Domain ↔ Persistence

- Entités Doctrine **≠** entités Domain.
- Mappers dédiés :
  - `UserMapper::toDomain(DoctrineUser $entity): DomainUser`,
  - `UserMapper::toDoctrine(DomainUser $user, ?DoctrineUser $entity): DoctrineUser`.
- Le mapper consomme des VOs Domain, appelle `DomainUser::reconstitute()` pour reconstruire l'agrégat **sans events**, et **préserve les timestamps** Domain.

---

## Index & contraintes Doctrine

**Do**

- Déclarer les index et contraintes uniques **dans l'entité Doctrine** via les attributs `#[ORM\Index]` / `#[ORM\UniqueConstraint]` (source de vérité), pas seulement dans la migration.
- Nommer explicitement en PascalCase :
  - index → `{Entité}{Colonnes}Idx` (ex. `ShopCustomerUserAccountIdx`, `ShopCartLineCartIdx`),
  - contraintes uniques → `{Entité}{Colonnes}Uniq` (ex. `ShopCartCustomerUniq`, `ShopCartLineProductUniq`).
- Déclarer **explicitement** les index implicites des clés étrangères (`ManyToOne` / `OneToOne`) pour figer leur nom.
- Garder des noms **identiques** entre les attributs d'entité et la migration.
- Vérifier l'absence de diff via `php bin/console doctrine:schema:update --dump-sql` après toute modification d'index.

**Don't**

- Laisser Doctrine générer un nom hashé (`IDX_…` / `UNIQ_…`) : cela crée un diff de renommage permanent au `schema:update`.
- Mettre un index récupérable en attribut uniquement dans la migration. Exceptions tolérées (sans équivalent attribut) : `CHECK` constraints et index fonctionnels (GIN/`trgm`, expressions).

---

## Gestion du temps

- `SystemClock` implémente `ClockInterface` :

```yaml
# config/services.yaml
services:
    App\Application\Shared\Port\ClockInterface:
        alias: App\Infrastructure\Service\SystemClock
```

- `new \DateTimeImmutable()` est **autorisé** dans Infrastructure (seule couche où l'horloge réelle est instanciée) :
  - `SystemClock::now()` → `new \DateTimeImmutable()`,
  - entités Doctrine (lifecycle callbacks, `prePersist`, constructeurs d'entités),
  - event subscribers, console commands.
- Domain et Application **ne doivent jamais** l'instancier directement (reçoivent `$now` ou injectent `ClockInterface`).

---

## Fixtures

- Chaque nouvelle feature persistée doit livrer ses fixtures `test` dans `infrastructure/src/DataFixtures/test/<Contexte>/`.
- Ajouter des fixtures `dev` uniquement si elles servent un parcours métier réaliste ou l'UX de développement ; ne pas préremplir un état qui doit naturellement démarrer vide (ex. panier utilisateur).
- Les fixtures d'une feature déclarent leurs dépendances via `DependentFixtureInterface` et réutilisent les références des fixtures amont (`CustomerFixtures`, `ProductFixtures`, etc.) au lieu de requêter une donnée arbitraire.
- Les fixtures `test` doivent être déterministes : références nommées, volumes limités, données stables pour les tests API/infra.
- Ajouter des références (`addReference`) aux entités seedées si une feature aval doit composer ses propres données.
- Toute fixture doit appartenir au bon groupe Doctrine Fixtures (`dev` ou `test`) via `FixtureGroupInterface`.

---

## Tests Infrastructure

Suites : `infra.persist`, `infra.command.user`, `infra.notif.user`, `infra.service.encoder`, `infra.service.token`, `infra.service.user` (cf. `AGENTS.md` racine).

---

## Checklist Infrastructure

- [ ] Chaque Port Application a une implémentation claire dans `infrastructure/...`.
- [ ] Le mapping Domain ↔ Doctrine est géré par des mappers dédiés (pas de raccourci).
- [ ] Aucun code Infra ne dépend de `presentation/`.
- [ ] Tous les bindings Ports → Implémentations sont dans `config/services.yaml`.
- [ ] Index/contraintes déclarés dans l'entité (`#[ORM\Index]`/`#[ORM\UniqueConstraint]`), nommés `…Idx`/`…Uniq`, identiques à la migration, sans diff `schema:update`.
- [ ] Toute nouvelle feature persistée fournit ses fixtures `test`, et ses fixtures `dev` seulement si elles sont utiles au parcours métier local.
