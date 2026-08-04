# AGENTS.md

> Guide pour humains **et** agents (Claude/Copilot/Cursor/LLM) : conventions transverses, architecture, workflow.
> Objectif : code **lisible**, **testable**, **orienté métier**, API **robuste**.
>
> **Ne consigner ici que des règles transverses et des résumés.** Toute règle spécifique à une couche doit être écrite — en détail — dans le `AGENTS.md` de cette couche, jamais dans ce fichier.
>
> **Règles spécifiques à une couche** → fichier `AGENTS.md` du dossier correspondant (chargé à la demande) :
> - [`domain/AGENTS.md`](domain/AGENTS.md) — DDD, agrégats, Value Objects, events, règles métier
> - [`application/AGENTS.md`](application/AGENTS.md) — use cases, CQRS, Ports, handlers
> - [`infrastructure/AGENTS.md`](infrastructure/AGENTS.md) — adapters, Doctrine, mappers, index
> - [`presentation/AGENTS.md`](presentation/AGENTS.md) — API Platform, DTOs, State, sécurité

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

Utiliser **`make`** (Docker = runtime par défaut) :

```bash
make install              # Build images, containers, vendors, init DB dev+test
make up / make down       # Docker up/down (down-hard pour prune images/volumes)
make serve-start / -stop  # Symfony local server si non Docker

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
- Découverte automatique par Messenger, avec convention obligatoire : `FooCommand` → `FooCommandHandler`, `BarQuery` → `BarQueryHandler`. Détails : [`application/AGENTS.md`](application/AGENTS.md).

### Structure des dossiers

```text
domain/         Cœur métier pur, par bounded context (Model/, ValueObject/, Event/, Exception/)
application/    Cas d'usage & orchestration (UseCase/Command|Query, Port/, Shared/CQRS)
infrastructure/ Adapters & implémentations (Persistence/, Service/, Notification/, Command/)
presentation/   Interface HTTP/API (ApiResource/, Dto/, State/, Presenter/, Security/, Validator/)
config/         Configuration Symfony · migrations/ · docker/ · src/ + tests/ : legacy (ne pas étendre)
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
| `domain/Shop` | `domain.shop` |
| `domain/User` | `domain.user` |
| `domain/SharedKernel` | `domain.shared` |
| `application/**/User/UseCase` | `appli.user` |
| `application/**/Shop/UseCase` + `Shared` | `appli.shop` |
| `infrastructure/**/Persistence` | `infra.persist` |
| `infrastructure/**/Command/User` | `infra.command.user` |
| `infrastructure/**/Notification/User` | `infra.notif.user` |
| `infrastructure/**/Service/Encoder` | `infra.service.encoder` |
| `infrastructure/**/Service/Token` | `infra.service.token` |
| `infrastructure/**/Service/User` | `infra.service.user` |
| `presentation/**/State/SendMail` | `pres.state.sendmail` |
| `presentation/**/State/Shared` | `pres.state.shared` |
| `presentation/**/State/User` | `pres.state.user` |
| `presentation/**/State/Shop` | `pres.state.shop` |
| `tests/Api/Shop` | `api.shop` |
| `presentation/tests/Api/User` | `api.user` |

- Les suites API (`api.*`) ne sont **pas** exécutables dans l'environnement courant.
- Ne pas ajouter de tests dans les dossiers exclus de `phpunit.dist.xml` (`<exclude>`) ; placer les nouveaux tests dans les suites existantes.
- DB de test dédiée, initialisée par `make install` — ne **jamais** réutiliser la DB de dev pour les tests.

---

## Git & PR workflow

- Branches : `main` (stable), `feat/*`, `fix/*`, `chore/*`.
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
- `docs/` : références techniques (`CQRS_messenger.md`, `varnish_cache.md`) et **audits** d'architecture datés dans `docs/audits/` (instantanés d'évaluation, **non normatifs** — les règles font foi dans les `AGENTS.md`).

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
