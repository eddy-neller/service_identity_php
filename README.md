## E.N Shop API – Backend e‑commerce avec Symfony 7 & API Platform 4

E.N Shop API est le **backend e‑commerce** du projet E.N Shop, construit avec **Symfony 7** et **API Platform 4**.  
Ce dépôt a été pensé comme un **projet portfolio** pour démontrer des compétences backend modernes : DDD, architecture hexagonale, validation stricte, documentation automatique REST/JSON:API et qualité de code industrielle.

---

## 🎯 Objectifs du projet

-   **Montrer la maîtrise de Symfony 7 et API Platform 4** pour exposer une API REST propre, documentée et sécurisable.
-   **Appliquer une architecture claire (domain / application / infrastructure / presentation)** pour une bonne séparation des responsabilités.
-   **Mettre en avant des bonnes pratiques de qualité** : tests, static analysis (PHPStan), normes de code (PHP-CS-Fixer/PHPCS), CI prête à l’emploi.
-   **S’intégrer dans un écosystème complet** : front Next.js (`en_shop_react`) + éventuelle interface d’admin.

En résumé : ce repo illustre comment je conçois une API maintenable pour un vrai produit e‑commerce.

---

## 🧩 Rôle de l’API dans l’écosystème

E.N Shop API fournit les **capabilités métier** pour :

-   Gérer le **catalogue produits** (produits, catégories, attributs, etc.).
-   Exposer des **endpoints REST** pour les frontends (public shop, admin, etc.).
-   Centraliser les **règles métier** côté backend (prix, statuts, etc.).
-   Servir de base à des fonctionnalités futures : panier, commandes, comptes clients, paiement, etc.

Le backend est pensé pour être **consommé par plusieurs clients** (web, admin, mobile) sans fuite de détails techniques côté front.

---

## 🛠️ Stack technique & outils

-   **PHP 8.4**
-   **Symfony 7.3**
-   **API Platform 4**
-   **Doctrine ORM**
-   **PostgreSQL** (selon configuration Docker)
-   **PHPUnit** pour les tests
-   **PHPStan** (analyses statiques, configuration stricte)
-   **PHP-CS-Fixer / PHPCS** (conventions de code)
-   **Docker / docker-compose** pour l’environnement de dev
-   **Makefile** pour centraliser les commandes de développement

Ces choix visent un environnement proche de la **production** (dev local facile, qualité contrôlée, automatisable en CI/CD).

---

## 📁 Architecture du projet

Le projet suit une organisation inspirée de l’architecture clean/hexagonale & DDD :

-   `src/Domain/` : **modèle métier**, entités, value objects, interfaces de repository, invariants.
-   `src/Application/` : **cas d’usage**, services applicatifs, orchestrations métier.
-   `src/Infrastructure/` : implémentations techniques (Doctrine, adapters, persistence, etc.).
-   `src/Presentation/` : exposition de l’API (API Platform, contrôleurs, DTO, sérialisation).

**Décision technique (en clair)** :  
Je sépare **métier**, **application** et **infrastructure** pour limiter le couplage et garder la possibilité de faire évoluer la persistance, le protocole ou le front sans casser tout le code métier.

---

## 🚀 Démarrage rapide

### Prérequis

-   **Docker** + **docker compose**
-   **Make** (pour utiliser le `Makefile`)

Aucun PHP local n’est requis : **toute la stack tourne dans Docker**, y compris l’application
(conteneur `app` = PHP 8.4-fpm piloté par supervisor, servi par `nginx`).

### Installation & lancement avec Docker

Depuis la racine du projet :

```bash
cp makefile.conf.dist makefile.conf
cp .env.dist .env   # puis remplacer les valeurs "change-me"
make install        # build des images, conteneurs, vendors, DB dev + test
make up             # démarre l'environnement
```

| Service | URL / port hôte |
|---|---|
| API (nginx → php-fpm) | `http://localhost:20900` — doc API Platform sur `/api` |
| Varnish (cache HTTP) | `http://localhost:20901` |
| PostgreSQL | `localhost:20902` |
| Redis | `localhost:20903` |
| Mailpit (webmail) | `http://localhost:20907` |
| RabbitMQ (management) | `http://localhost:20909` |

Commandes utiles : `make bash-app` (shell dans le conteneur), `make console c="debug:router"`,
`make logs s=app`, `make unit`.

> Les ports publiés sont pilotés par les variables `*_EXPOSED_PORT` de `.env` ; les services
> communiquent entre eux par leur nom de conteneur sur le réseau compose.

---

## 🔌 Points d’entrée principaux de l’API

Selon ta configuration API Platform, tu trouveras (à titre d’exemple) :

-   **Ressources catalogue** : produits, catégories, etc.
-   **Opérations de lecture/écriture** : recherche de produits, création/mise à jour par l’admin, etc.

Les ressources et endpoints sont décrits via les **attributs PHP** d’API Platform, ce qui permet une **documentation automatique** et un contrat d’API clair.

> Remarque : la liste exacte des endpoints évolue avec le projet, mais le style reste : ressources bien nommées, opérations explicites, validation forte.

---

## ✅ Qualité de code & outillage

-   **Normes de code** :
    -   `phpcs.xml.dist`, `.php-cs-fixer.dist.php`, `ruleset.xml` pour imposer une convention homogène.
-   **Analyse statique** :
    -   `phpstan.neon` / `phpstan.dist.neon` pour garder un niveau de confiance élevé sur le typage et les contrats.
-   **Tests** :
    -   `phpunit.dist.xml` pour la configuration des tests.
-   **Automatisation** :
    -   `Makefile` pour lancer rapidement : tests, cs-fix, analyse statique, etc.
    -   (Optionnel) `grumphp.yml` pour exécuter les checks en **pre-commit**.

Exemples de commandes utiles (via `make`) :

```bash
make cs           # vérifie les standards de code
make cs-fix       # corrige automatiquement le style
make phpstan      # lance l’analyse statique
make test         # exécute la suite de tests
```

**Pourquoi autant d’outils ?**  
Parce que pour un backend métier, c’est ce qui permet de garder un **code de production propre** sur la durée (DRY, KISS, peu de dette technique).

---

## 🔍 Dossiers intéressants

-   `src/Domain/` : voir comment le métier est modélisé (entités, valeurs, invariants).
-   `src/Application/` : cas d’usage et orchestration métier.
-   `src/Infrastructure/` : implémentations concrètes (Doctrine, adaptateurs).

Ces emplacements reflètent ma façon de :

-   **Nommer le code** de manière explicite.
-   **Séparer la logique** métier de la technique.
-   **Préparer un projet** pour être maintenu en équipe.

---

## 🧭 Pistes d’évolution (roadmap)

-   Ajout complet du **panier** et des **commandes** (avec statut, paiement, etc.).
-   Gestion des **comptes clients** et de l’authentification (JWT / OAuth2 / Keycloak, etc.).
-   Intégration avec un **front Next.js** (projet `en_shop_react`) pour un parcours utilisateur de bout en bout.
-   Mise en place d’une **CI GitLab** qui exécute tests, PHPStan, CS-Fixer à chaque push.
-   Rendre l’exécution applicative **répliquable** : séparer PHP-FPM, workers Messenger et cron ;
    permettre plusieurs replicas PHP-FPM en Docker Compose et préparer un déploiement conteneurisé
    compatible avec un orchestrateur.

L’idée est de montrer que l’API a été pensée pour **grandir proprement**.

---

## 📄 Licence

Ce projet est publié sous **licence propriétaire** (voir le fichier `LICENSE` à la racine du dépôt).
Tous droits réservés. Ce code est fourni à titre d'exemple pour un portfolio et ne peut être utilisé, modifié ou distribué sans autorisation.

---

## 👤 À propos du développeur

Ce projet fait partie d’un **portfolio professionnel** orienté “vrai produit” plutôt que “petits exemples”.  
Il illustre ma manière de :

-   Concevoir une **API** maintenable.
-   Structurer un code **orienté métier** et non purement technique.
-   Mettre en place une **boîte à outils de qualité** (tests, static analysis, conventions de code) prête pour la production.

N’hésite pas à parcourir les autres dépôts associés (front `en_shop_react`, admin, etc.) pour avoir une vision **full‑stack** de l’écosystème E.N Shop.
