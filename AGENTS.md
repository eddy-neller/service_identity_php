# AGENTS.md

> Guide pour humains **et** agents (Claude/Copilot/Cursor/LLM) : conventions transverses, architecture, workflow.
> Objectif : code **lisible**, **testable**, **orienté métier**, API **robuste**.
>
> **Ne consigner ici que des règles transverses et des résumés.** Toute règle spécifique à une couche doit être écrite — en détail — dans le `AGENTS.md` de cette couche, jamais dans ce fichier.
>
> **Règles spécifiques à une couche** → fichier `AGENTS.md` du dossier correspondant (chargé à la demande) :
> - [`src/Domain/AGENTS.md`](src/Domain/AGENTS.md) — DDD, agrégats, Value Objects, events, règles métier
> - [`src/Application/AGENTS.md`](src/Application/AGENTS.md) — use cases, CQRS, Ports, handlers
> - [`src/Infrastructure/AGENTS.md`](src/Infrastructure/AGENTS.md) — adapters, Doctrine, mappers, index
> - [`src/Presentation/AGENTS.md`](src/Presentation/AGENTS.md) — API Platform, DTOs, State, sécurité

---

## Stack & versions

- **PHP** 8.4 (`declare(strict_types=1);` obligatoire dans tout fichier PHP)
- **Symfony** 7.4 · **API Platform** 4.2 · **Doctrine ORM** + Migrations
- **Tests** : PHPUnit + DAMA DoctrineTestBundle
- **Qualité** : PHPStan, PHP-CS-Fixer, Rector, PHPCS, PhpMD
- Ne pas introduire de dépendances imposant PHP < 8, Symfony < 7 ou API Platform < 4.
- Utiliser les attributs PHP natifs (Doctrine mapping, API Platform Metadata, listeners/decorators Symfony).

---

## Commandes principales

Utiliser **`make`** — **tout s'exécute dans le conteneur `app`** (`docker compose exec app`).
Ne jamais lancer `composer`, `bin/console` ou `vendor/bin/*` directement sur l'hôte.

```bash
make install              # Build images, containers, vendors, init DB dev+test
make reinstall            # Recrée les DB dev+test (migrations + fixtures) sans rebuild
make up / make down       # Docker up/down (down-hard pour prune images/volumes)
make bash-app             # Shell dans le conteneur applicatif
make console c="…"        # bin/console dans le conteneur
make logs s=app           # Logs d'un service

# Qualité
make stan                 # PHPStan
make phpcsfixer_dry       # PHP-CS-Fixer dry-run
make phpcsfixer_fix       # PHP-CS-Fixer auto-fix
make phpcs                # PHPCS
make phpmd                # PhpMD
make rector-dry / rector  # Rector dry-run / auto-fix

# Tests
make unit                         # Suite complète
make unit-filter f=ClassNameTest  # Test ciblé
make unit-suite s=<suite>         # Suite ciblée (voir table)
make unit-coverage                # Coverage HTML dans coverage/
```

### Topologie Docker

```text
navigateur ──> varnish:20901 ──> nginx:20900 ──> app (php-fpm:9000)
                                                  ├─ database (postgres)
                                                  ├─ rabbitmq · redis
                                                  └─ mailer (mailpit:20907)
```

- `app` = image PHP 8.4-fpm pilotée par **supervisor** : php-fpm + cron + workers Messenger
  (`async` et `domain_events`). Le code est bind-monté sur `/var/www`.
- Dans `.env`, les hôtes sont les **noms de services** (`database`, `rabbitmq`, `redis`, `mailer`,
  `varnish`) avec leurs ports internes ; les variables `*_EXPOSED_PORT` ne servent qu'à publier
  les ports sur la machine hôte (accès depuis un client SQL, Mailpit, etc.).

---

## Architecture (Clean Architecture + DDD)

```text
Presentation  →  Application  →  Domain
                     ↓
                  Ports (interfaces)
                     ↑
              Infrastructure (adapters)
```

### Règles d'or (dépendances)

- **Domain** : PHP pur uniquement. Aucune dépendance vers Application/Infrastructure/Presentation ni framework (Symfony, Doctrine, API Platform, Ramsey).
- **Application** : dépend de Domain + Ports. Jamais de Presentation/Infrastructure ni framework. **Orchestration uniquement, pas de logique métier.**
- **Infrastructure** : implémente les Ports. Dépend de Domain + frameworks. Jamais de Presentation.
- **Presentation** : expose l'API, parle à Application **uniquement via les Buses CQRS** + DTOs. Jamais d'accès direct aux repos/services Infrastructure ni aux implémentations concrètes des Ports.

### CQRS — mantra transverse

- Tout cas d'usage passe par `CommandBusInterface` (écritures) / `QueryBusInterface` (lectures).
- **« Toujours via le Bus, jamais via le Handler »** : aucun code hors Application n'appelle `handle()`.
- Découverte automatique par Messenger, avec convention obligatoire : `FooCommand` → `FooCommandHandler`, `BarQuery` → `BarQueryHandler`. Détails : [`src/Application/AGENTS.md`](src/Application/AGENTS.md).

### Structure des dossiers

```text
src/Domain/         Cœur métier pur, par bounded context (Model/, ValueObject/, Event/, Exception/)
src/Application/    Cas d'usage & orchestration (UseCase/Command|Query, Port/, Shared/CQRS)
src/Infrastructure/ Adapters & implémentations (Persistence/, Service/, Notification/, Command/)
src/Presentation/   Interface HTTP/API (ApiResource/, Dto/, State/, Presenter/, Security/, Validator/)
tests/              Tests des couches, organisés de la même façon que le code source
```

---

## Conventions de code

- PSR-12 + conventions Symfony : indentation 4 espaces, 1 classe/fichier, types de retour explicites.
- Nommage : Classes/interfaces `PascalCase` · Méthodes/propriétés/paramètres `camelCase` · Constantes `UPPER_SNAKE_CASE` · Clés d'env/config `SNAKE_CASE`.
- Services `final` et `readonly` (handlers, providers, processors, adapters).
- `mixed` uniquement aux frontières (`ProcessorInterface`, `ProviderInterface`).
- Imports `use` explicites (pas de FQCN inline). Après un move/rename, vérifier et ajuster les `use` en haut de fichier.

---

## Tests — suites par périmètre

Lancer la suite correspondante **avant chaque livraison** si le périmètre est touché (`make unit-suite s=...`).

| Périmètre modifié | Suite |
|---|---|
| `src/Domain/Shop` | `domain.shop` |
| `src/Domain/User` | `domain.user` |
| `src/Domain/SharedKernel` | `domain.shared` |
| `src/Application/**/User/UseCase` | `appli.user` |
| `src/Application/**/Shop/UseCase` + `Shared` | `appli.shop` |
| `src/Infrastructure/Adapter/Hasher` + `security.password_hashers` | `infra.adapter.hasher` |
| `src/Infrastructure/Adapter/Storage` | `infra.adapter.storage` |
| `src/Infrastructure/Adapter/Token` | `infra.adapter.token` |
| `src/Infrastructure/ApiPlatform` | `infra.api_platform` |
| `src/Infrastructure/Http/ShopService` | `infra.http.shop_service` |
| `src/Infrastructure/Persistence` (Doctrine réel) | `infra.persist` |
| `src/Infrastructure/Symfony/Command` | `infra.symfony.command` |
| `src/Infrastructure/Symfony/EventSubscriber` | `infra.symfony.event_subscriber` |
| `src/Infrastructure/Symfony/Messenger/CQRS` | `infra.symfony.messenger.cqrs` |
| `src/Infrastructure/Symfony/Messenger/Event` | `infra.symfony.messenger.event` |
| `src/Infrastructure/Symfony/Security` | `infra.symfony.security` |
| `src/Infrastructure/Symfony/Service/Notification` | `infra.symfony.service.notification` |
| `src/Presentation/**/State/SendMail` | `pres.state.sendmail` |
| `src/Presentation/**/State/Shared` | `pres.state.shared` |
| `src/Presentation/**/State/User` | `pres.state.user` |
| `src/Presentation/**/State/Shop` | `pres.state.shop` |
| `tests/Presentation/Api/Shop/CategoryTest.php` | `api.shop.category` |
| `tests/Presentation/Api/Shop/ProductTest.php` | `api.shop.product` |
| `tests/Presentation/Api/User` | `api.user` |

- Les suites API (`api.*`) sont exécutables dès que la stack Docker tourne et que la DB de test
  est initialisée (`make up` + `make install`) : elles émettent de vraies requêtes HTTP in-process
  contre `shop_test`. Sans conteneurs ni DB de test, elles échouent en bloc — vérifier
  `docker compose ps` avant d'en conclure quoi que ce soit. Il n'existe pas de suite `api.shop`
  globale : le périmètre `tests/Presentation/Api/Shop` se découpe en suites `api.shop.*`.
- **`tests/Unit/` vs `tests/Integration/`** : un test qui boote le kernel Symfony, touche la DB ou lit le
  conteneur DI est un test d'**intégration** → `tests/Integration/`. `tests/Unit/` n'accueille que des
  `PHPUnit\Framework\TestCase` sans kernel (doubles pour toutes les dépendances).
- Ne pas ajouter de tests dans les dossiers exclus de `phpunit.dist.xml` (`<exclude>`) ; placer les nouveaux tests dans les suites existantes.
- DB de test dédiée, initialisée par `make install` — ne **jamais** réutiliser la DB de dev pour les tests.

---

## Git & PR workflow

- Branches : `main` (stable), `feat/*`, `fix/*`, `chore/*`.
- La branche `main` est protégée : avant tout commit local, créer et utiliser une branche
  dédiée (`feat/*`, `fix/*` ou `chore/*`) adaptée au périmètre.
- Commits : sujet impératif ≤ 70 chars (« Add … », « Fix … », « Refactor … »). Body pour contexte, breaking changes, décisions d'architecture.
- Ne jamais committer de secrets (`.env`, `makefile.conf`, secrets CI). `.env.test` = valeurs par défaut test uniquement.
- `.env` est l’unique configuration locale, ignorée par Git ; `.env.dist` est son modèle complet et versionné.
- Si les ports/services Docker changent : mettre à jour **à la fois** `.env`, `.env.dist` ET `docker-compose*.yml`.

---

## Performance & observabilité

- Collections toujours paginées.
- Éviter N+1 (joins, fetch modes, DTO read model).
- Cache (HTTP / Symfony Cache) si pertinent — cf. `docs/varnish_cache.md`.
- Logs structurés et corrélables (request ID si possible).

---

## Documentation

- `README.md` : quickstart, env, commandes, architecture courte.
- `docs/` : références techniques (`CQRS_messenger.md`, `domain_events.md`, `varnish_cache.md`) et **audits** d'architecture datés dans `docs/audits/` (instantanés d'évaluation, **non normatifs** — les règles font foi dans les `AGENTS.md`).

## Référence nouvelle API

- La « nouvelle API » désigne le projet NestJS voisin : [`../api_js`](../api_js).
- Pour aligner un comportement avec elle, consulter en priorité `src/application/` (cas d'usage), `src/presentation/http/` (contrats et validations) et `test/` (comportement attendu).


---

## Git et hygiene

- Convention Git locale : pour committer, utiliser `git cm "<message>"`; pour pousser, utiliser `git psa && git fa`.

---

## Checklist architecture (transverse)

- [ ] Aucun `use App\Application\*`, `App\Infrastructure\*`, `App\Presentation\*` dans Domain.
- [ ] Aucun import Symfony/Doctrine/API Platform/Ramsey dans Domain ni Application.
- [ ] Presentation communique uniquement via les Buses (pas d'injection de Handlers).
- [ ] Infrastructure ne dépend pas de Presentation.
- [ ] Chaque Port Application a son implémentation dans Infrastructure avec binding dans `config/services.yaml`.
- [ ] `declare(strict_types=1);` dans tout nouveau fichier PHP.
- [ ] `make stan` et `make phpcsfixer_dry` passent ; suite(s) de tests concernée(s) vertes.

> Checklists détaillées par couche : voir le `AGENTS.md` de chaque dossier.
