# Évaluation Clean Architecture - Couche Presentation

## 📊 Note globale : **9.5/10**

---

## 📝 Dernières modifications documentées

**Date de mise à jour** : Décembre 2025

**État actuel confirmé** :

1. ✅ **Utilisation des Buses CQRS** : Tous les Processors/Providers utilisent `CommandBusInterface` et `QueryBusInterface` pour communiquer avec Application
2. ✅ **Adaptation des fichiers** : `SymfonyFileAdapter` convertit les fichiers Symfony en `FileInterface` à la frontière Presentation/Application
3. ✅ **Presenters dédiés** : `UserResourcePresenter` transforme les Outputs Application en ressources API Platform
4. ✅ **Validation et sécurité** : Gestion complète via Symfony et API Platform
5. ✅ **Structure organisée** : Séparation claire par bounded context (User, Shared)
6. ✅ **Indépendance Infrastructure** : Aucune dépendance directe aux repositories ou services Infrastructure

**Principe architectural confirmé** : La couche Presentation reste **indépendante de l'infrastructure** et communique avec Application **uniquement via les Buses CQRS** et les DTOs (Commands/Queries/Outputs).

---

## 🎯 Principes Clean Architecture évalués

### 1. **Dépendance vers Application** ⭐⭐⭐⭐⭐

**Principe** : Presentation doit dépendre d'Application via les CommandBus/QueryBus et les DTOs (Commands/Queries/Outputs).

**Évaluation** :

-   ✅ **Utilisation des Buses** : `CommandBusInterface` et `QueryBusInterface` pour communiquer avec Application
-   ✅ **Utilisation des Commands/Queries** : Création et dispatch des DTOs applicatifs
-   ✅ **Utilisation des Outputs** : Récupération des résultats via les Outputs
-   ✅ **Pas de dépendance directe aux Handlers** : Presentation ne connaît pas les implémentations
-   ✅ **Adaptation des fichiers** : Utilisation de `SymfonyFileAdapter` pour convertir les fichiers Symfony en `FileInterface` avant de les passer à Application
-   ⚠️ **Dépendance à Domain** : Utilisation directe de `UserId` (value object) pour construire les Commands/Queries

**Exemple** :

```php
// ✅ BON : Utilise CommandBus pour communiquer avec Application
final class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus, // ✅ Interface Application
    ) {}

    public function process(mixed $data, Operation $operation, ...): mixed
    {
        $command = new RegisterUserCommand( // ✅ DTO Application
            email: $data->email,
            username: $data->username,
            // ...
        );

        $output = $this->commandBus->dispatch($command); // ✅ Via Bus
        return $this->userResourcePresenter->toResource($output->user);
    }
}

// ✅ BON : Adaptation des fichiers Symfony vers FileInterface
final class UserAvatarProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, ...): mixed
    {
        // Adapter le File Symfony en FileInterface pour Application
        $fileAdapter = new SymfonyFileAdapter($data->avatarFile); // ✅ Adaptation à la frontière
        $command = new UploadAndUpdateAvatarCommand(
            userId: $userId,
            avatarFile: $fileAdapter, // ✅ FileInterface, pas Symfony\File
        );
        // ...
    }
}
```

**Note** : **9/10** - Bonne utilisation des Buses, mais dépendance directe à Domain pour `UserId`. Adaptation correcte des fichiers Symfony vers `FileInterface`.

---

### 2. **Indépendance de Infrastructure** ⭐⭐⭐⭐⭐

**Principe** : Presentation ne doit pas dépendre directement d'Infrastructure.

**Évaluation** :

-   ✅ **Pas de dépendance aux repositories** : Aucun repository Infrastructure utilisé directement
-   ✅ **Pas de dépendance aux services** : Aucun service Infrastructure utilisé directement
-   ✅ **Utilisation des Ports** : `AvatarUrlResolverInterface` utilisé via le Presenter
-   ✅ **Ressources API Platform** : Les ressources API Platform peuvent utiliser des entités Doctrine pour la sérialisation (détail d'implémentation API Platform)

**Note** : **10/10** - Aucune dépendance problématique à Infrastructure. Les ressources API Platform utilisent des entités Doctrine uniquement pour la sérialisation, ce qui est acceptable dans le contexte d'API Platform.

---

### 3. **Dépendance vers Domain** ⭐⭐⭐⭐

**Principe** : Presentation peut dépendre du Domain uniquement pour construire les Commands/Queries (value objects), pas pour la logique métier.

**Évaluation** :

-   ✅ **Value objects utilisés** : `UserId` pour construire les Commands/Queries
-   ✅ **Pas de logique métier** : Aucune logique métier dans Presentation
-   ✅ **Utilisation limitée** : Seulement pour la construction des DTOs Application
-   ⚠️ **Présenters utilisent Domain** : `UserResourcePresenter` utilise `DomainUser` directement

**Exemple** :

```php
// ✅ BON : Utilise UserId (value object) pour construire la Query
final class UserGetProvider implements ProviderInterface
{
    public function provide(...): array|null|object
    {
        $userId = UserId::fromString($uriVariables['id']); // ✅ Value object
        $query = new DisplayUserQuery($userId);
        $output = $this->queryBus->dispatch($query);
        return $this->userResourcePresenter->toResource($output->user);
    }
}

// ⚠️ ACCEPTABLE : Presenter utilise DomainUser
final class UserResourcePresenter
{
    public function toResource(DomainUser $user): UserResource // ✅ Domain pour transformation
    {
        // Transformation Domain → Presentation
    }
}
```

**Note** : **9/10** - Utilisation correcte, mais dépendance à Domain dans les Presenters (acceptable pour la transformation).

---

### 4. **Utilisation des frameworks** ⭐⭐⭐⭐⭐

**Principe** : Presentation peut et doit utiliser les frameworks (API Platform, Symfony) pour exposer l'API.

**Évaluation** :

-   ✅ **API Platform** : Utilisé pour les ressources, opérations, filtres
-   ✅ **Symfony** : Utilisé pour la validation, sécurité, sérialisation
-   ✅ **Processors/Providers** : Implémentation des interfaces API Platform
-   ✅ **Sérialisation** : Utilisation des groupes de sérialisation Symfony
-   ✅ **Sécurité** : Utilisation de Symfony Security pour l'authentification

**Exemples** :

```php
// ✅ BON : Utilise API Platform pour exposer l'API
#[ApiResource(
    operations: [
        new Get(provider: UserGetProvider::class),
        new Post(processor: UserRegisterProcessor::class),
    ]
)]
class UserResource
{
    // ...
}

// ✅ BON : Utilise Symfony pour la validation
class UserRegisterInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
```

**Note** : **10/10** - Utilisation appropriée des frameworks.

---

### 5. **Transformation des données** ⭐⭐⭐⭐⭐

**Principe** : Presentation doit transformer les DTOs Application (Outputs) en ressources API Platform.

**Évaluation** :

-   ✅ **Presenters dédiés** : `UserResourcePresenter` pour la transformation
-   ✅ **Séparation claire** : Logique de transformation isolée
-   ✅ **Transformation bidirectionnelle** : Input → Command, Output → Resource
-   ✅ **Pas de logique métier** : Seulement de la transformation de données
-   ✅ **Adaptation des fichiers** : `SymfonyFileAdapter` convertit les fichiers Symfony en `FileInterface` à la frontière

**Structure** :

```
Presentation/
├── User/
│   ├── Dto/                    # DTOs d'entrée (Input)
│   │   └── UserRegisterInput.php
│   ├── State/                  # Processors/Providers
│   │   └── UserRegisterProcessor.php
│   ├── Presenter/              # Transformation Output → Resource
│   │   └── UserResourcePresenter.php
│   └── ApiResource/           # Resources API Platform
│       └── UserResource.php
├── Shared/
│   └── Adapter/                # Adapters pour découpler des frameworks
│       └── SymfonyFileAdapter.php  # Adapte File Symfony → FileInterface
```

**Exemple** :

```php
// ✅ BON : Presenter transforme Output Application → Presentation
final class UserResourcePresenter
{
    public function __construct(
        private readonly AvatarUrlResolverInterface $avatarUrlResolver,
    ) {}

    public function toResource(DomainUser $user): UserResource
    {
        $resource = new UserResource();
        $resource->id = $user->getId()?->toString() ?? '';
        $resource->username = $user->getUsername()->toString();
        $resource->email = $user->getEmail()->toString();
        $resource->firstname = $user->getFirstname()?->toString();
        $resource->lastname = $user->getLastname()?->toString();
        $resource->roles = $user->getRoles()->all();
        $resource->status = $user->getStatus()->toInt();
        $resource->avatarUrl = $this->avatarUrlResolver->resolve($user->getAvatar());
        $resource->createdAt = $user->getCreatedAt();
        $resource->updatedAt = $user->getUpdatedAt();

        return $resource;
    }
}
```

**Points importants** :

-   ✅ **Transformation complète** : Tous les getters du Domain sont utilisés
-   ✅ **Timestamps inclus** : `createdAt` et `updatedAt` sont exposés dans l'API
-   ✅ **Value Objects convertis** : `Username`, `EmailAddress`, etc. sont convertis en string
-   ✅ **Service Infrastructure** : `AvatarUrlResolverInterface` utilisé via injection (Port Application)

**Note** : **10/10** - Transformation correcte et bien organisée.

---

### 6. **Séparation des responsabilités** ⭐⭐⭐⭐⭐

**Principe** : Chaque composant Presentation a une responsabilité claire.

**Évaluation** :

-   ✅ **ApiResource** : Définition des endpoints API Platform
-   ✅ **DTOs (Input)** : Validation et réception des données HTTP
-   ✅ **Processors** : Transformation Input → Command et dispatch
-   ✅ **Providers** : Dispatch Query et transformation Output → Resource
-   ✅ **Presenters** : Transformation Domain → Resource
-   ✅ **Validators** : Validation métier spécifique à la présentation

**Structure** :

```
Presentation/
├── User/
│   ├── ApiResource/           # Définition des endpoints
│   ├── Dto/                   # DTOs d'entrée (validation)
│   ├── State/                # Processors/Providers (orchestration)
│   ├── Presenter/           # Transformation Domain → Resource
│   ├── Security/            # Sécurité spécifique
│   └── Validator/           # Validateurs personnalisés
└── Shared/
    └── State/                # Composants partagés
```

**Note** : **10/10** - Séparation claire des responsabilités.

---

### 7. **Validation** ⭐⭐⭐⭐⭐

**Principe** : La validation doit être dans Presentation (validation des données HTTP).

**Évaluation** :

-   ✅ **Validation Symfony** : Utilisation des contraintes Symfony
-   ✅ **Validateurs personnalisés** : `EmailNotExists`, `UsernameNotExists`
-   ✅ **Validation dans les DTOs** : Attributs de validation sur les propriétés
-   ✅ **Séparation** : Validation présentation vs validation métier (Domain)

**Exemple** :

```php
// ✅ BON : Validation dans les DTOs Presentation
class UserRegisterInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[AppAssert\EmailNotExists()] // ✅ Validateur personnalisé
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 20)]
    #[AppAssert\UsernameNotExists()] // ✅ Validateur personnalisé
    public string $username;
}
```

**Note** : **10/10** - Validation correcte et bien organisée.

---

### 8. **Sécurité** ⭐⭐⭐⭐⭐

**Principe** : La sécurité doit être gérée dans Presentation (authentification, autorisation).

**Évaluation** :

-   ✅ **Symfony Security** : Utilisation de `Security` pour l'authentification
-   ✅ **API Platform Security** : Configuration dans les opérations API Platform
-   ✅ **Traits de sécurité** : `UserMeSecurityTrait` pour la réutilisation
-   ✅ **Séparation** : Sécurité présentation vs sécurité métier (Domain)

**Exemple** :

```php
// ✅ BON : Sécurité dans les opérations API Platform
new Get(
    uriTemplate: '/users/me',
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    provider: UserMeProvider::class,
)

// ✅ BON : Utilisation de Security dans les Processors
final class UserMeAvatarProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait; // ✅ Trait de sécurité

    public function __construct(
        private Security $security, // ✅ Symfony Security
    ) {}
}
```

**Note** : **10/10** - Sécurité correctement gérée.

---

### 9. **Testabilité** ⭐⭐⭐⭐

**Principe** : Les composants Presentation doivent être testables.

**Évaluation** :

-   ✅ **Processors testables** : Injection de `CommandBusInterface` (mockable)
-   ✅ **Providers testables** : Injection de `QueryBusInterface` (mockable)
-   ✅ **Presenters testables** : Logique de transformation isolée
-   ⚠️ **Dépendance à API Platform** : Nécessite API Platform pour les tests d'intégration
-   ⚠️ **Dépendance à Symfony** : Nécessite Symfony pour les tests d'intégration

**Note** : **8/10** - Bonne testabilité, mais dépendance aux frameworks pour les tests d'intégration.

---

## ⚠️ Points d'amélioration

### 1. **Dépendance à Domain dans Presenters** 🟡 **MINEUR**

**Problème** : Les Presenters utilisent directement `DomainUser`.

**Impact** :

-   ⚠️ Dépendance à Domain (acceptable pour la transformation)
-   ⚠️ Mais pourrait être évitée en utilisant les Outputs Application

**Solution recommandée** :

-   Utiliser les Outputs Application au lieu de Domain directement
-   Ou créer des DTOs Presentation intermédiaires

**Note** : Acceptable, mais pourrait être amélioré.

---

### 2. **Utilisation directe de UserId** 🟡 **MINEUR**

**Problème** : Les Processors/Providers utilisent directement `UserId::fromString()`.

**Impact** :

-   ⚠️ Dépendance directe à Domain
-   ⚠️ Mais acceptable car c'est un value object simple

**Solution recommandée** :

-   Créer des factories dans Application pour construire les Commands/Queries
-   Ou accepter cette dépendance (value objects simples)

**Note** : Acceptable, c'est une dépendance minimale.

---

## 📋 Détail de la notation

| Critère                            | Note  | Commentaire                                                                                  |
| ---------------------------------- | ----- | -------------------------------------------------------------------------------------------- |
| **Dépendance vers Application**    | 9/10  | Utilise CommandBus/QueryBus, mais dépendance directe à Domain (UserId)                       |
| **Indépendance de Infrastructure** | 10/10 | Aucune dépendance problématique, utilisation d'entités Doctrine acceptable pour API Platform |
| **Dépendance vers Domain**         | 9/10  | Utilisation correcte pour value objects et transformation                                    |
| **Utilisation des frameworks**     | 10/10 | Utilisation appropriée d'API Platform et Symfony                                             |
| **Transformation des données**     | 10/10 | Transformation correcte via Presenters, timestamps inclus                                    |
| **Séparation des responsabilités** | 10/10 | Structure claire, responsabilités bien définies                                              |
| **Validation**                     | 10/10 | Validation correcte dans les DTOs                                                            |
| **Sécurité**                       | 10/10 | Sécurité correctement gérée via Symfony Security                                             |
| **Testabilité**                    | 8/10  | Bonne testabilité, mais dépendance aux frameworks pour tests d'intégration                   |

**Moyenne** : **9.6/10** → **9.5/10** (arrondi)

---

## 🎯 Structure de la couche Presentation

### Organisation

```
Presentation/
├── User/                      # Bounded context User
│   ├── ApiResource/          # Définition des endpoints API Platform
│   │   └── UserResource.php
│   ├── Dto/                  # DTOs d'entrée (validation)
│   │   ├── UserRegisterInput.php
│   │   ├── UserPostInput.php
│   │   └── ...
│   ├── State/                # Processors/Providers (orchestration)
│   │   ├── UserRegisterProcessor.php
│   │   ├── UserGetProvider.php
│   │   └── Me/
│   │       ├── UserMeProvider.php
│   │       └── UserMeAvatarProcessor.php
│   ├── Presenter/            # Transformation Domain → Resource
│   │   └── UserResourcePresenter.php
│   ├── Security/             # Sécurité spécifique
│   │   └── UserMeSecurityTrait.php
│   └── Validator/            # Validateurs personnalisés
│       ├── EmailNotExists.php
│       └── UsernameNotExists.php
│
├── Shop/                      # Bounded context Shop (si applicable)
│   └── ...
│
└── Shared/                    # Composants partagés
    ├── Adapter/               # Adapters pour découpler des frameworks
    │   └── SymfonyFileAdapter.php  # Adapte File Symfony → FileInterface
    └── State/
        └── PaginatedCollectionProvider.php
```

### Rappel : placement de `UserResource`, des DTO et des State

-   **`UserResource` (`Presentation/User/ApiResource/UserResource.php`)**

    -   ✅ Correctement placé dans **Presentation** : c’est une ressource API Platform, donc purement orientée **HTTP / contrat d’API** (endpoints, sécurité, OpenAPI, groupes de sérialisation).
    -   ✅ Peut référencer l’entité Doctrine (`stateOptions: new Options(entityClass: User::class)`) : c’est un **détail d’implémentation API Platform** acceptable tant qu’aucune logique métier n’est mise dans la ressource.

-   **DTO d’entrée (`Presentation/User/Dto/*Input.php`)**

    -   ✅ Correctement placés dans **Dto/** : ils représentent la **forme des requêtes HTTP** (body, validation Symfony), pas le modèle métier.
    -   ✅ Leur rôle est de :
        -   recevoir les données HTTP,
        -   appliquer la validation Symfony,
        -   être transformés en **Commands/Queries Application** dans les Processors.

-   **Validateurs Symfony (`Presentation/User/Validator/*Validator.php` + contraintes `*.php`)**

    -   ✅ Correctement placés dans **Validator/** : ce sont des **contraintes Symfony personnalisées** et leurs validateurs associés (`EmailNotExists`, `UsernameNotExists`, `CurrentPassword`, etc.).
    -   ✅ Leur rôle est de :
        -   encapsuler des règles de **validation côté Presentation** (ex. “email non utilisé”, “username non utilisé”, “mot de passe actuel correct”),
        -   être utilisés via les attributs `#[AppAssert\...]` dans les DTO d’entrée,
        -   ne pas contenir de logique métier complexe : uniquement de la validation HTTP / ergonomie d’API.
        -   **important** : pour les règles métier (unicité email/username), ces validateurs sont une **validation de surface** pour l’UX, mais **la vraie garantie** doit rester dans :
            -   Domain/Application (invariants vérifiés dans les use cases),
            -   et la base de données (index uniques, contraintes).

-   **States (Processors / Providers) (`Presentation/User/State/*`)**
    -   ✅ Correctement placés dans **State/** : ce sont les adaptateurs API Platform qui font le lien entre HTTP et Application.
    -   ✅ Règles à respecter :
        -   parlent à Application **uniquement via** `CommandBusInterface` / `QueryBusInterface`,
        -   **n’injectent jamais** de repository Doctrine ni de service d’`infrastructure/*`,
        -   ne contiennent **aucune logique métier**, seulement de l’orchestration (Input → Command, Query → Output → Presenter).

> Ce rappel garantit que, même avec API Platform, la couche Presentation reste un **pur adaptateur HTTP** : les ApiResource définissent le contrat, les DTO gèrent la validation, les State orchestrent via les Buses, et toute la logique métier reste dans Domain/Application.

### Flux de dépendances

```
┌─────────────────────────────────────────────────────────┐
│                    Presentation                         │
│                                                         │
│  ┌──────────────────────────────────────┐             │
│  │  API Resources (API Platform)          │             │
│  │  - UserResource                        │             │
│  └──────────────────────────────────────┘             │
│         │                                                │
│         │ utilise                                        │
│         ▼                                                │
│  ┌──────────────────────────────────────┐             │
│  │  Processors/Providers                 │             │
│  │  - UserRegisterProcessor              │             │
│  │  - UserGetProvider                    │             │
│  └──────────────────────────────────────┘             │
│         │                                                │
│         │ utilise                                        │
│         ▼                                                │
│  ┌──────────────────────────────────────┐             │
│  │  CommandBus/QueryBus (Application)    │             │
│  │  - CommandBusInterface                │             │
│  │  - QueryBusInterface                  │             │
│  └──────────────────────────────────────┘             │
│         │                                                │
│         │ dispatch                                       │
│         ▼                                                │
│  ┌──────────────────────────────────────┐             │
│  │  Application                          │             │
│  │  - Commands/Queries                   │             │
│  │  - Outputs                            │             │
│  └──────────────────────────────────────┘             │
│                                                         │
│  Utilise : API Platform, Symfony (frameworks)          │
└─────────────────────────────────────────────────────────┘
```

---

## ✅ Points forts

### 1. **Utilisation correcte des Buses**

-   ✅ Communication avec Application via CommandBus/QueryBus
-   ✅ Pas de dépendance directe aux Handlers
-   ✅ Séparation claire des responsabilités
-   ✅ Adaptation des fichiers Symfony vers `FileInterface` à la frontière

### 2. **Transformation des données**

-   ✅ Presenters dédiés pour la transformation
-   ✅ Séparation Input → Command, Output → Resource
-   ✅ Logique de transformation isolée

### 3. **Validation et sécurité**

-   ✅ Validation dans les DTOs
-   ✅ Sécurité via Symfony Security
-   ✅ Validateurs personnalisés

### 4. **Structure claire**

-   ✅ Organisation par bounded context
-   ✅ Séparation Processors/Providers/Presenters
-   ✅ Composants partagés dans Shared

### 5. **Utilisation appropriée des frameworks**

-   ✅ API Platform pour l'exposition de l'API
-   ✅ Symfony pour la validation et la sécurité
-   ✅ Encapsulation correcte

---

## ⚠️ Points d'amélioration

### 1. **Dépendance Domain dans Presenters** 🟡

**Impact** : Dépendance à Domain (acceptable, mais pourrait être améliorée).

**Recommandation** : Utiliser les Outputs Application au lieu de Domain directement.

---

### 3. **Tests d'intégration** 🟢

**Impact** : Nécessité d'API Platform et Symfony pour les tests d'intégration.

**Recommandation** : Utiliser des tests fonctionnels avec API Platform Test Client.

---

## 📊 Comparaison avec les principes Clean Architecture

| Principe Clean Architecture        | Respecté   | Note  |
| ---------------------------------- | ---------- | ----- |
| **Dépendance vers Application**    | ✅ Oui     | 9/10  |
| **Indépendance de Infrastructure** | ✅ Oui     | 10/10 |
| **Dépendance vers Domain**         | ✅ Oui     | 9/10  |
| **Utilisation des frameworks**     | ✅ Oui     | 10/10 |
| **Transformation des données**     | ✅ Oui     | 10/10 |
| **Séparation des responsabilités** | ✅ Oui     | 10/10 |
| **Validation**                     | ✅ Oui     | 10/10 |
| **Sécurité**                       | ✅ Oui     | 10/10 |
| **Testabilité**                    | ⚠️ Partiel | 8/10  |

---

## ✅ Conclusion

**Note finale : 9.5/10**

La couche Presentation respecte **excellemment** les principes de Clean Architecture :

**Points forts** :

-   ✅ **Communication via Buses CQRS** : Utilisation de `CommandBusInterface` et `QueryBusInterface` pour communiquer avec Application
-   ✅ **Transformation via Presenters** : `UserResourcePresenter` transforme les entités Domain en ressources API Platform
-   ✅ **Timestamps exposés** : `createdAt` et `updatedAt` sont correctement exposés dans l'API
-   ✅ **Validation et sécurité** : Gestion complète via Symfony et API Platform
-   ✅ **Structure claire** : Organisation par bounded context avec séparation Processors/Providers/Presenters
-   ✅ **Utilisation appropriée des frameworks** : API Platform et Symfony utilisés correctement
-   ✅ **Séparation des responsabilités** : Chaque composant a une responsabilité claire et unique
-   ✅ **Adaptation à la frontière** : `SymfonyFileAdapter` convertit les fichiers Symfony vers `FileInterface` (découplage parfait)
-   ✅ **Aucune dépendance problématique** : Pas de dépendance directe à Infrastructure (utilisation d'entités Doctrine acceptable pour API Platform)

**Points à améliorer** :

-   🟡 `UserResourcePresenter` utilise directement `DomainUser` (acceptable, mais pourrait être amélioré)
-   🟡 Utilisation directe de `UserId` dans les Processors/Providers (acceptable pour un value object)

**Comparaison avec les meilleures pratiques** :

| Aspect                          | État       |
| ------------------------------- | ---------- |
| **Utilisation des Buses**       | ✅ Parfait |
| **Transformation des données**  | ✅ Parfait |
| **Validation et sécurité**      | ✅ Parfait |
| **Indépendance Infrastructure** | ✅ Parfait |
| **Structure**                   | ✅ Parfait |
| **Testabilité**                 | ⚠️ Bon     |

L'architecture est **excellente** et respecte les principes de Clean Architecture. La couche Presentation joue correctement son rôle d'interface entre l'API (API Platform) et la couche Application. L'utilisation d'entités Doctrine dans les ressources API Platform est acceptable car c'est un détail d'implémentation du framework et n'affecte pas l'indépendance des couches applicatives.

**Cohérence avec les autres couches** :

-   ✅ **Application** : Communication via CommandBus/QueryBus uniquement, pas de dépendance directe aux Handlers
-   ✅ **Domain** : Utilisation minimale et justifiée (value objects pour construire les Commands/Queries)
-   ✅ **Infrastructure** : Aucune dépendance directe, utilisation de Ports quand nécessaire
-   ✅ **Adaptation à la frontière** : `SymfonyFileAdapter` convertit les fichiers Symfony en `FileInterface` avant de les passer à Application
-   ✅ **Timestamps exposés** : Les `createdAt` et `updatedAt` sont correctement exposés dans l'API via les Presenters

**État actuel** : Architecture stable et production-ready avec une séparation claire des responsabilités. La couche Presentation gère correctement l'exposition de l'API (endpoints, validation, sécurité) et communique avec Application uniquement via les abstractions (Buses CQRS).

**Statistiques de l'architecture** :

-   **Bounded contexts** : User + Shared
-   **Processors** : 10+ (RegisterUser, UpdatePassword, UpdateAvatar, DeleteUser, etc.)
-   **Providers** : 5+ (UserGet, UserGetCollection, UserMe, etc.)
-   **Presenters** : 1 (`UserResourcePresenter`)
-   **Validateurs personnalisés** : 2 (`EmailNotExists`, `UsernameNotExists`)
-   **Adapters** : 1 (`SymfonyFileAdapter` - découplage parfait)
-   **Communication** : 100% via CommandBus/QueryBus (aucun appel direct aux Handlers)
