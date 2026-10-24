# Infrastructure Layer – Adapters & Frameworks

> **But** : implémenter les Ports Application et encapsuler les frameworks.
> Couche `src/Infrastructure/`. Règles transverses : voir `AGENTS.md` racine.

---

## Rôle

- Implémenter **tous les Ports** Application : repos, hashers, file storage, email, queues, etc.
- Encapsuler : Doctrine (ORM, migrations), Symfony (services, events, console), Ramsey (UUID), HTTP clients, queues, FS, etc.
- Implémenter les buses CQRS avec les bus Symfony Messenger synchrones et héberger leurs middlewares techniques.
- Héberger les **handlers de messages techniques Messenger** (`#[AsMessageHandler]`) : adaptation/IO uniquement, l'orchestration métier reste en Application.

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
| `DomainEventBusInterface` | `MessengerDomainEventBus` |
| `UserRepositoryInterface` | `DoctrineUserRepository` |
| `PasswordHasherInterface` | `SymfonyPasswordHasherAdapter` |
| `TokenProviderInterface` | `TokenProvider` |
| `AvatarUploaderInterface` | `AvatarUploader` |

> **Règle** : interface dans `src/Application/…/Port`, implémentation + dépendances framework dans `src/Infrastructure/...`, **binding dans `config/services.yaml`**.

### Contrats internes à Infrastructure (≠ Ports)

Une interface dont **aucun** use case / service applicatif n'est consommateur n'est pas un Port : elle
reste dans `src/Infrastructure/`, à côté de son implémentation.

| Contrat interne | Implémentation | Consommateurs |
|---|---|---|
| `Adapter\Cache\QueryCacheInterface` | `SymfonyTagAwareQueryCache` | `QueryCacheMiddleware`, `CacheInvalidationMiddleware` |
| `Adapter\Uuid\UuidGeneratorInterface` | `RamseyUuidGenerator` | repositories Doctrine |
| `Adapter\Token\AuthVersionStoreInterface` | `RedisAuthVersionStore` | `JwtAuthVersionSubscriber`, `LexikJwtAccessTokenProvider` |
| `Symfony\Service\Notification\User\UserNotifierInterface` | `UserNotifier` | handlers `Symfony\Messenger\Event\Handler\User` |
| `Symfony\Messenger\Event\DomainEventLedgerInterface` | `Persistence\Doctrine\Inbox\DoctrineDomainEventLedger` | handlers `Symfony\Messenger\Event\Handler` |

> Avant de créer une interface dans `src/Application/…/Port`, vérifier qu'elle est bien injectée par un
> handler ou un service applicatif. Sinon → `src/Infrastructure/`.

### Où vit l'accès Doctrine

**Un fichier qui connaît le schéma vit dans `src/Infrastructure/Persistence/`.** Le critère n'est pas
« est-ce une entité ? » mais « ce fichier nomme-t-il une table, une colonne, ou du SQL dialectal ? ».

C'est pour cela que `DoctrineDomainEventLedger` a quitté `Symfony/Messenger/Event/` : il porte le nom
de la table `processed_domain_event` et un `ON CONFLICT` propre à PostgreSQL. Avec la migration, ce
sont les deux seuls fichiers à dépendre de cette table ; les garder voisins évite qu'un renommage
oblige à fouiller un dossier `Symfony/`. Son **interface**, elle, reste auprès de ses consommateurs :
c'est le contrat, il ne connaît aucun schéma.

**Co-localiser interface et implémentation est la norme** — c'est ce que font `Adapter/Cache/`,
`Adapter/Token/` et `Adapter/Uuid/`, dossiers nommés par la **fonction**, l'implémentation nommée par
la technologie. Ça ne pose aucun problème là : Redis, le cache Symfony et Ramsey ne nomment ni table
ni colonne.

**Sauf quand l'implémentation connaît le schéma** : elle part alors dans `Persistence/`, et le
contrat reste auprès de ses consommateurs. C'est déjà le cas des repositories —
`UserRepositoryInterface` dans `Application/User/Port/`, `DoctrineUserRepository` dans
`Persistence/Doctrine/` — et c'est pour la même raison que `DomainEventLedgerInterface` reste dans
`Symfony/Messenger/Event/`. La mettre auprès de son adaptateur donnerait aux handlers un
`use App\Infrastructure\Persistence\Doctrine\Inbox\DomainEventLedgerInterface` : la flèche de
dépendance s'inverserait, le nom laisserait croire que Doctrine fait partie du contrat, et un second
adaptateur — une inbox Redis, un double en mémoire — n'aurait plus de place logique.

Vérification, qui doit ne rendre que les exceptions ci-dessous :

```bash
grep -rn '^use Doctrine' src/ | grep -v 'src/Infrastructure/Persistence'
```

- **Exception assumée** : les fixtures (`Symfony/DataFixtures/**`) étendent `Fixture` et reçoivent un
  `ObjectManager` — c'est le contrat du bundle, il n'y a pas de version sans Doctrine.
- **Écart connu, à corriger** : `Symfony/Command/User/GenerateUserImagesCommand` injecte
  l'`EntityManager` et modifie des entités Doctrine directement, sans passer par le Domain. Le
  corriger n'est pas un déplacement de fichier mais l'extraction d'un cas d'usage — à traiter pour
  lui-même, pas au détour d'un autre chantier.

---

## Domain Events : outbox et idempotence

Les use cases publient leurs Domain Events **dans** leur transaction via `DomainEventBusInterface`
(cf. `src/Application/AGENTS.md`). `MessengerDomainEventBus` les écrit dans l'outbox Doctrine
(`messenger_messages`, queue `domain_events`) sur la connexion transactionnelle courante : agrégat et
événements sont commités ensemble. Les réactions ne s'exécutent qu'au worker
`messenger:consume domain_events`.

Règles pour un handler d'événement (`src/Infrastructure/Symfony/Messenger/Event/Handler/`) :

- Un handler par réaction, annoté `#[AsMessageHandler(bus: 'event.bus')]` — pas de subscriber monolithique.
- **Effet en base** : `markProcessed()` dans la même transaction que l'effet.
- **Effet externe** (e-mail, Redis) : `hasProcessed()` en garde d'entrée, `markProcessed()` après succès.
- **Journalisation seule** : pas de ledger, l'effet est idempotent par nature.
- Une situation métier attendue (token périmé ou remplacé, agrégat disparu) est un **no-op** : la
  journaliser, la marquer comme traitée, puis retourner normalement. Réserver
  `UnrecoverableMessageHandlingException` à un message réellement invalide, jamais à un état métier normal.
- Une **dépendance momentanément injoignable**, reconnue comme telle par son adaptateur, se rend à
  Messenger en `RecoverableMessageHandlingException` : rejouée sans limite de nombre, elle se rattrape
  seule au retour de la dépendance. Jamais pour un échec quelconque, qui n'atteindrait alors jamais la
  file d'échec. Exemple et raisons : `PublishUserIntegrationEventHandler`.
- **Un appel réseau d'un worker est borné dans le temps**, sans quoi une connexion coupée sans
  réponse le bloque jusqu'à `default_socket_timeout` (300 s) et retient tous les messages derrière
  lui. Le client SNS passe par `BoundedSnsClientFactory` (10 s) ; ne pas le reconstruire par simple
  autowiring, qui lui retirerait aussi les nouvelles tentatives d'AsyncAws. Gardé par
  `SnsClientWiringTest` (`infra.messaging`).
- Tout handler consommant un transport asynchrone porte `sign: true` dans `#[AsMessageHandler]` :
  Messenger vérifie ainsi l'HMAC du corps avant le handler. La signature protège l'intégrité, jamais
  la confidentialité ; les messages restent dépourvus de secrets.
- Les événements sont sérialisés par le `PhpSerializer` de Messenger : **vider `domain_events` et `failed_domain_events`
  avant tout renommage ou déplacement** d'une classe d'événement ou d'un Value Object qu'elle transporte.
- **Un `messenger:failed:retry` qui échoue de nouveau laisse le message dans sa file d'échec** : c'est
  `Symfony/Messenger/Failure/KeepFailedReplayInFailureTransportListener` qui le garantit. Sans lui,
  Messenger supprimait un rejeu raté depuis `failed_domain_events`, sa propre file d'échec, et rangeait
  dans celle-ci un rejeu raté depuis `failed_async`. Ne pas « corriger » en déclarant une file comme sa
  propre `failure_transport` : c'est exactement le cas où Messenger renonce.

> Référence complète (outbox, worker, régimes d'idempotence, diagnostic, recettes) :
> [`docs/domain_events.md`](../../docs/domain_events.md).

### Déduplication des e-mails à token

`UserNotifier` envoie les e-mails d'activation et de réinitialisation directement depuis le worker
`domain_events`, pour que le lien contenant le token ne soit jamais sérialisé dans un transport.
Il utilise un verrou Symfony (`LockFactory`) partagé via Redis, avec une clé
`user.<canal>.<userId>.<hash du token>`, afin d'écarter les doublons concurrents. `flock` ne convient
pas à cette coordination entre workers ou conteneurs.

---

## Mapping Domain ↔ Persistence

- Entités Doctrine **≠** entités Domain.
- Les entités Doctrine vivent dans `src/Infrastructure/Persistence/Doctrine/<Contexte>/...`, à côté de
  leurs repositories et mappers, et leur nom se termine obligatoirement par `Entity`
  (`UserEntity`, `ProductEntity`, etc.).
- Elles sont exclues de l'autowiring et de l'autoconfiguration par le glob
  `src/Infrastructure/Persistence/Doctrine/**/*Entity.php` dans `config/services.yaml` : ne pas les
  enregistrer comme services.
- Le mapping Doctrine couvre `src/Infrastructure/Persistence/Doctrine` avec le préfixe
  `App\Infrastructure\Persistence\Doctrine` ; il ne retient que les classes portant des attributs ORM.
- Mappers dédiés :
  - `UserMapper::toDomain(DoctrineUser $entity): DomainUser`,
  - `UserMapper::toDoctrine(DomainUser $user, ?DoctrineUser $entity): DoctrineUser`.
- Le mapper consomme des VOs Domain, appelle `DomainUser::reconstitute()` pour reconstruire l'agrégat **sans events**, et **préserve les timestamps** Domain.

### Champs immuables : n'écrire que sur la branche de création

L'UnitOfWork compare les champs par **identité stricte** (`!==`), pas par valeur. Un champ typé objet
réaffecté à chaque mapping (`Uuid::fromString(...)`, VO reconstruit…) produit une nouvelle instance :
égale par valeur, différente par identité. Doctrine le voit donc comme **modifié à chaque `save()`**.

Conséquences, toutes silencieuses :

- un `UPDATE` est émis même quand rien n'a changé ;
- Gedmo `Timestampable(on: 'update')` se déclenche, donc **`updatedAt` ne veut plus dire « dernière
  modification » mais « dernier appel à `save()` »** — toute logique métier ou tout tri qui s'appuie
  dessus devient faux ;
- le vrai changeset est noyé, ce qui masque les autres champs faussement sales.

**Do**

- Affecter l'identifiant **uniquement** dans la branche de création :

```php
public function toDoctrine(DomainFoo $foo, ?DoctrineFoo $entity = null): DoctrineFoo
{
    if (null === $entity) {
        $entity = new DoctrineFoo();
        $entity->setId(Uuid::fromString($foo->getId()->toString()));
    }

    $entity->setLabel($foo->getLabel()->toString()); // champs mutables : toujours réaffectés

    return $entity;
}
```

- Traiter de la même façon **tout champ objet immuable** — un rattachement fixé à la création dont le
  modèle de domaine n'expose aucun mutateur (ex. `Customer::$userAccountId`).
- Pour un agrégat **entièrement** immuable (ex. `RefreshToken` : émis puis supprimé, jamais modifié),
  ne pas prévoir de chemin « entité managée » du tout : `toDoctrine()` ne prend pas de second paramètre
  et le `save()` du repository ne fait **pas** de `find()` préalable. L'identifiant venant de
  `nextIdentity()`, cette lecture est un aller-retour SQL perdant par construction ; un identifiant
  déjà pris doit échouer bruyamment plutôt qu'écraser en silence.

**Don't**

- `$entity ??= new DoctrineFoo();` suivi d'un `setId(...)` inconditionnel : c'est la forme exacte qui
  crée le faux positif.
- Se fier au fait que « ça marche » : le symptôme n'est visible ni dans les tests ni à l'œil nu, seulement
  dans le profiler SQL sous la forme d'un `UPDATE … SET id = ?, …`.

**Vérifier**

Sur un agrégat chargé depuis la base, un mapping aller-retour sans modification métier doit produire un
changeset **vide** :

```php
$mapper->toDoctrine($mapper->toDomain($entity), $entity);
$em->persist($entity);
$em->getUnitOfWork()->computeChangeSets();
$em->getUnitOfWork()->getEntityChangeSet($entity); // doit être []
```

> Attention : sur une entité **fraîchement créée**, les collections sont déjà initialisées et le
> changeset est trompeur. Toujours recharger depuis la base (`$em->clear()` puis `find()`) avant de
> mesurer — c'est le seul état qui reproduit un vrai cas d'usage.

---

## Pagination

**`Paginator` : préciser `fetchJoinCollection`**

`new Paginator($qb)` laisse le second argument à `true`. Ce mode n'est nécessaire que si la DQL
fetch-join une association **to-many** : sans lui, le `LIMIT` porterait sur des lignes dupliquées par
le produit cartésien. Doctrine passe alors par un détour en **3 requêtes** — comptage, puis ids
distincts de la page, puis `SELECT … WHERE id IN (?, ? … × itemsPerPage)`.

Aucune requête paginée du projet ne fetch-join de collection : toutes passent `false`, ce qui ramène
à **2 requêtes**. Un fetch-join **to-one** (`ManyToOne`, ex. `p.category` dans
`DoctrineProductRepository`) ne duplique pas les lignes et n'impose donc pas `true` — le fetch-join
reste actif, sans N+1 sur l'association.

> À l'ajout d'une nouvelle requête paginée : passer `false` par défaut, et ne repasser à `true` que
> si un `OneToMany` / `ManyToMany` est fetch-joint. Vérifier alors qu'une page ne contient pas de
> doublon et que deux pages consécutives ne se recouvrent pas.

**`setUseOutputWalkers(false)` quand la requête s'y prête**

Avec `fetchJoinCollection: false`, les *output walkers* ne pilotent plus que la requête de comptage.
Laissés actifs, ils produisent un COUNT à triple imbrication dont la sous-requête interne sélectionne
**toutes les colonnes** de l'entité (mot de passe compris) juste pour être comptées :

```sql
SELECT COUNT(*) FROM (SELECT DISTINCT id_0 FROM (SELECT u0_.id, u0_.firstname, …, u0_.password FROM "user" u0_))
```

Désactivés, Doctrine utilise `CountWalker` et génère `SELECT count(DISTINCT u0_.id) FROM "user" u0_`.

Condition de sûreté : `CountWalker` ne sait pas traiter un `HAVING`, et un `ORDER BY` sur une
expression aliasée ou calculée doit rester du ressort des output walkers. Aucune requête paginée du
projet n'est dans ce cas — les champs triables sont tous des colonnes d'entité, éventuellement d'une
entité jointe en to-one (`u.username` pour Customer, `c.title` pour Product), ce qui reste supporté.

> **Vérifier après coup, pas seulement à la lecture** : le comptage est ce que change ce réglage.
> Comparer les en-têtes `X-Total-Count` / `X-Total-Pages` avant et après, cache vidé, sur chaque
> collection touchée.

**Toujours terminer le tri par une clé totale**

Un `ORDER BY` sur une colonne non unique ne définit pas un ordre complet : PostgreSQL ne garantit
pas l'ordre relatif des ex aequo, et `LIMIT/OFFSET` s'appuie sur deux requêtes distinctes. Une même
ligne peut donc apparaître sur deux pages, une autre sur aucune. Le tri par défaut des listes est
`createdAt DESC`, or `created_at` est un `timestamp(0)` — **précision à la seconde**, donc les ex
aequo sont possibles dès que deux lignes sont créées dans la même seconde.

Chaque `applyOrdering()` termine donc par l'identifiant :

```php
foreach ($orderBy as $field => $direction) {
    // … tri demandé par le client …
}

$qb->addOrderBy('u.id', 'ASC');
```

Le tri client reste prioritaire ; l'identifiant n'intervient qu'en départage.

> Symptôme si on l'oublie : intermittent, invisible en test, et il n'apparaît qu'à partir du moment
> où le plan change (tri externe, workers parallèles, bascule en index scan). Ne pas attendre de
> pouvoir le reproduire pour l'appliquer — sur les jeux de données actuels, il n'y a aucun ex aequo
> et le défaut est indétectable.

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

- Chaque nouvelle feature persistée doit livrer ses fixtures `test` dans `src/Infrastructure/Symfony/DataFixtures/test/<Contexte>/`.
- Ajouter des fixtures `dev` uniquement si elles servent un parcours métier réaliste ou l'UX de développement ; ne pas préremplir un état qui doit naturellement démarrer vide (ex. panier utilisateur).
- Les fixtures d'une feature déclarent leurs dépendances via `DependentFixtureInterface` et réutilisent les références des fixtures amont (`CustomerFixtures`, `ProductFixtures`, etc.) au lieu de requêter une donnée arbitraire.
- Les fixtures `test` doivent être déterministes : références nommées, volumes limités, données stables pour les tests API/infra.
- Ajouter des références (`addReference`) aux entités seedées si une feature aval doit composer ses propres données.
- Toute fixture doit appartenir au bon groupe Doctrine Fixtures (`dev` ou `test`) via `FixtureGroupInterface`.

---

## Tests Infrastructure

Deux périmètres, jamais mélangés :

- **`tests/Infrastructure/Unit/`** — `PHPUnit\Framework\TestCase` **uniquement**. Aucun `bootKernel()`,
  aucun accès conteneur, aucune DB : l'adapter est instancié à la main avec des doubles (`ArrayAdapter`,
  stubs de `EntityManagerInterface`, etc.). Suites : `infra.api_platform`, `infra.adapter.storage`,
  `infra.adapter.token`, `infra.symfony.command`, `infra.symfony.event_subscriber`,
  `infra.symfony.messenger.cqrs`, `infra.symfony.messenger.event`, `infra.symfony.messenger.failure`,
  `infra.symfony.security`, `infra.symfony.service.notification`.
- **`tests/Infrastructure/Integration/`** — `KernelTestCase` : ce qu'on ne peut vérifier qu'avec le vrai
  Doctrine ou le vrai conteneur (requêtes DQL, mappers, outbox/ledger, câblage Messenger et bindings de
  Ports). Suites : `infra.adapter.hasher`, `infra.persist`, `infra.symfony.messenger.cqrs`,
  `infra.symfony.messenger.event`.

> Règle : si un test a besoin du kernel, il n'a rien à faire dans `Unit/`. Inversement, un test qui boote
> le kernel sans jamais s'en servir doit passer en `TestCase`.

---

## Checklist Infrastructure

- [ ] Chaque Port Application a une implémentation claire dans `src/Infrastructure/...`.
- [ ] Le mapping Domain ↔ Doctrine est géré par des mappers dédiés (pas de raccourci).
- [ ] Identifiants et champs objets immuables affectés **uniquement** dans la branche de création du mapper ; un mapping aller-retour sans modification métier produit un changeset vide.
- [ ] Aucun côté inverse `OneToMany` déclaré sans code qui le lit ; aucune entité Doctrine exposée en `#[ApiResource]`.
- [ ] Aucun code Infra ne dépend de `src/Presentation/`.
- [ ] Tous les bindings Ports → Implémentations sont dans `config/services.yaml`.
- [ ] Index/contraintes déclarés dans l'entité (`#[ORM\Index]`/`#[ORM\UniqueConstraint]`), nommés `…Idx`/`…Uniq`, identiques à la migration, sans diff `schema:update`.
- [ ] Toute nouvelle feature persistée fournit ses fixtures `test`, et ses fixtures `dev` seulement si elles sont utiles au parcours métier local.
