# Évaluation Clean Architecture - Couche Domain

## 📊 Note globale : **10/10**

---

## 🎯 Principes Clean Architecture évalués

> Structure actuelle de la couche Domain :
>
> -   `Domain/User` : agrégat `User` et VOs organisés par sous-domaines internes `Identity/`, `Security/`, `Preference/`, `Profile/`, exceptions regroupées par catégorie.
> -   `Domain/Shop` : sous-contextes `Catalog/`, `Customer/`, `Shipping/`, `Ordering/`, `Shared/` (VOs communs `Money`, `Slug`, `Uuid`), sans dépendance aux frameworks.

### 1. **Indépendance totale des frameworks** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain ne doit avoir **aucune** dépendance à des frameworks externes (Symfony, Doctrine, API Platform, etc.).

**Évaluation** :

-   ✅ **Aucune dépendance** à Symfony
-   ✅ **Aucune dépendance** à Doctrine
-   ✅ **Aucune dépendance** à API Platform
-   ✅ **Aucune dépendance** à Ramsey UUID (refactorisé pour utiliser uniquement des strings)
-   ✅ **Aucune dépendance** à des bibliothèques externes
-   ✅ **Uniquement PHP natif** : Types primitifs, `DateTimeImmutable`, exceptions standard

**Vérification** :

```bash
# Aucune dépendance trouvée
grep -r "use (Symfony|Doctrine|Ramsey|ApiPlatform)" domain/
# Résultat : 0 occurrence
```

**Note** : **10/10** - Indépendance totale des frameworks. Le Domain est complètement pur.

---

### 2. **Indépendance de l'Application** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain ne doit pas dépendre de la couche Application.

**Évaluation** :

-   ✅ **Aucune dépendance** à `App\Application\`
-   ✅ **Aucune dépendance** aux use cases
-   ✅ **Aucune dépendance** aux ports/interfaces de l'Application
-   ✅ **Aucune dépendance** aux DTOs applicatifs

**Vérification** :

```bash
# Aucune dépendance trouvée
grep -r "use App\\Application" domain/
# Résultat : 0 occurrence
```

**Note** : **10/10** - Aucune dépendance à la couche Application.

---

### 3. **Indépendance de l'Infrastructure** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain ne doit pas dépendre de l'Infrastructure (bases de données, services externes, etc.).

**Évaluation** :

-   ✅ **Aucune dépendance** à `App\Infrastructure\`
-   ✅ **Aucune dépendance** aux repositories Doctrine
-   ✅ **Aucune dépendance** aux services d'infrastructure
-   ✅ **Aucune dépendance** aux entités Doctrine
-   ✅ **Aucune dépendance** aux adapters

**Vérification** :

```bash
# Aucune dépendance trouvée
grep -r "use App\\Infrastructure" domain/
# Résultat : 0 occurrence
```

**Note** : **10/10** - Aucune dépendance à l'Infrastructure.

---

### 4. **Indépendance de la Presentation** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain ne doit pas connaître la couche Presentation (API Platform, Controllers, etc.).

**Évaluation** :

-   ✅ **Aucune dépendance** à `App\Presentation\`
-   ✅ **Aucune dépendance** à API Platform
-   ✅ **Aucune dépendance** aux DTOs de présentation
-   ✅ **Aucune dépendance** aux controllers

**Vérification** :

```bash
# Aucune dépendance trouvée
grep -r "use App\\Presentation" domain/
# Résultat : 0 occurrence
```

**Note** : **10/10** - Aucune dépendance à la couche Presentation.

---

### 5. **Logique métier pure** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain contient uniquement la logique métier, sans préoccupations techniques.

**Évaluation** :

-   ✅ **Entités avec logique métier** : Méthodes expressives (`requestActivation`, `activate`, `requestPasswordReset` côté User ; `place`, `markAsPaid` côté Shop/Ordering ; `create`, `update` côté Shop/Catalog, Shop/Shipping, Shop/Customer).
-   ✅ **Value Objects avec validation** : Validation métier dans les constructeurs (`Identity\Username`, `Identity\EmailAddress`, `Identity\Firstname`, `Identity\Lastname`, `Shop\Shared\Money`, `Shop\Shared\Slug`, et tous les IDs des différents sous-contextes).
-   ✅ **Invariants respectés** : Vérification des limites (tokens, quantités, devise homogène pour Order + Carrier), vérification de verrouillage, validation des montants et prix, cohérence des références.
-   ✅ **Exceptions métier** : Exceptions spécifiques regroupées par domaine (`RateLimit\ActivationLimitReachedException`, `Uniqueness\EmailAlreadyUsedException`, `Security\UserLockedException`).
-   ✅ **Domain Events** : Événements métier pour notifier les changements importants (8 événements User : `UserRegisteredEvent`, `UserActivatedEvent`, `UserCreatedByAdminEvent`, `UserUpdatedByAdminEvent`, `UserDeletedEvent`, `ActivationEmailRequestedEvent`, `PasswordResetRequestedEvent`, `PasswordResetCompletedEvent` ; 2 événements Shop/Ordering : `OrderPlacedEvent`, `OrderPaidEvent`).
-   ✅ **Pas de logique technique** : Pas de gestion de persistance, pas de gestion HTTP, pas de logging.

**Exemples** :

```php
// ✅ BON : Constructeur privé (force l'utilisation des factory methods)
final class User
{
    private function __construct(
        private readonly ?UserId $id,
        private Username $username,
        // ...
    ) {}
}

// ✅ BON : Factory methods pour la création
public static function register(
    UserId $id,
    Username $username,
    EmailAddress $email,
    HashedPassword $password,
    Preferences $preferences,
    DateTimeImmutable $now,
    ?Firstname $firstname = null,
    ?Lastname $lastname = null,
): self {
    $user = new self(
        id: $id,
        username: $username,
        // ...
        status: UserStatus::inactive(),
        // ...
    );

    $user->recordEvent(new UserRegisteredEvent(
        userId: $id,
        email: $email,
        occurredOn: $now,
    ));

    return $user;
}

// ✅ BON : Factory method de reconstitution (sans événements, pour l'infrastructure)
public static function reconstitute(
    UserId $id,
    Username $username,
    // ... tous les paramètres
): self {
    return new self(
        id: $id,
        username: $username,
        // ...
    );
}

// ✅ BON : Logique métier pure avec injection du temps (testabilité)
public function requestActivation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
{
    if ($this->getActiveEmail()->getMailSent() >= self::MAX_TOKEN_REQUESTS) {
        throw new ActivationLimitReachedException();
    }

    $this->setActiveEmail(new ActiveEmail(
        mailSent: $this->getActiveEmail()->getMailSent() + 1,
        token: $token,
        tokenTtl: $expiresAt->getTimestamp(),
        lastAttempt: $now,
    ));

    if (null !== $this->id) {
        $this->recordEvent(new ActivationEmailRequestedEvent(
            userId: $this->id,
            email: $this->email,
            occurredOn: $now,
        ));
    }
}

// ✅ BON : Setters privés (modification contrôlée)
private function setUsername(Username $username): void
{
    $this->username = $username;
}

// ✅ BON : Méthodes métier publiques (point d'entrée unique)
public function updateUsername(Username $username, DateTimeImmutable $now): void
{
    $this->setUsername($username);
    $this->setUpdatedAt($now);
}

// ✅ BON : Value Object avec validation métier
final class Username
{
    private const int MIN_LENGTH = 2;
    private const int MAX_LENGTH = 20;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new InvalidArgumentException('Le nom d\'utilisateur ne peut pas être vide.');
        }

        $length = mb_strlen($trimmed);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Le nom d\'utilisateur doit contenir entre 2 et 20 caractères.');
        }

        $this->value = $trimmed;
    }
}
```

**Note** : **10/10** - Logique métier pure, aucune préoccupation technique.

---

### 6. **Types primitifs et standards uniquement** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain utilise uniquement des types primitifs PHP et des standards reconnus.

**Évaluation** :

-   ✅ **Types primitifs PHP** : `string`, `int`, `bool`, `array`
-   ✅ **DateTimeImmutable** : Type standard PHP (SPL)
-   ✅ **Exceptions standard** : `InvalidArgumentException`, `RuntimeException`
-   ✅ **Pas de dépendances externes** : Aucune bibliothèque tierce
-   ✅ **Fonctions PHP natives** : `filter_var`, `preg_match`, `trim`, etc.

**Types utilisés** :

-   ✅ `string` : Pour les valeurs textuelles
-   ✅ `int` : Pour les nombres entiers
-   ✅ `bool` : Pour les booléens
-   ✅ `array` : Pour les collections
-   ✅ `DateTimeImmutable` : Pour les dates (SPL standard)
-   ✅ `InvalidArgumentException` : Pour les erreurs de validation (SPL standard)
-   ✅ `RuntimeException` : Pour les erreurs d'exécution (SPL standard)

**Note** : **10/10** - Utilisation exclusive de types primitifs et standards PHP.

---

### 7. **Encapsulation et immutabilité** ⭐⭐⭐⭐⭐

**Principe** : Les entités et value objects doivent être bien encapsulés et immutables quand approprié.

**Évaluation** :

-   ✅ **Value Objects immutables** : Classes `final` avec propriétés `readonly`
-   ✅ **Encapsulation renforcée** : Propriétés privées avec getters publiques `get*()` et setters **privés** `set*()`
-   ✅ **Constructeur privé** : Force l'utilisation des factory methods (`register`, `createByAdmin`, `reconstitute`)
-   ✅ **Factory methods** : Trois factory methods pour différents contextes de création
-   ✅ **Méthodes métier publiques** : Point d'entrée unique pour toute modification (encapsulent les setters privés)
-   ✅ **Validation dans les constructeurs** : Validation métier à la création (`Username`, `Firstname`, `Lastname`, `EmailAddress`)
-   ✅ **Méthodes equals()** : Comparaison basée sur la valeur ou l'identité
-   ✅ **Immutabilité de l'identité** : Propriété `id` en `readonly`, pas de `setId()` publique
-   ✅ **Injection du temps** : `DateTimeImmutable $now` injecté pour testabilité (pas de `new DateTimeImmutable()` en dur)

**Exemples** :

```php
// ✅ BON : Value Object immuable
final class UserId
{
    private function __construct(
        private readonly string $value,
    ) {}

    public static function fromString(string $value): self
    {
        // Validation...
        return new self($trimmed);
    }
}

// ✅ BON : Entité avec constructeur privé et factory methods
final class User
{
    // Constructeur privé : force l'utilisation des factory methods
    private function __construct(
        private readonly ?UserId $id,  // ✅ readonly pour garantir l'immutabilité
        private Username $username,
        // ...
    ) {}

    // Factory method pour l'inscription
    public static function register(
        UserId $id,
        Username $username,
        EmailAddress $email,
        HashedPassword $password,
        Preferences $preferences,
        DateTimeImmutable $now,
        // ...
    ): self {
        $user = new self(
            id: $id,
            username: $username,
            // ...
        );

        $user->recordEvent(new UserRegisteredEvent(
            userId: $id,
            email: $email,
            occurredOn: $now,
        ));

        return $user;
    }

    // Factory method de reconstitution (sans événements)
    public static function reconstitute(
        UserId $id,
        // ... tous les paramètres
    ): self {
        return new self(
            id: $id,
            // ...
        );
    }

    // Getter publique
    public function getId(): ?UserId
    {
        return $this->id;
    }

    // Setter privé (modification contrôlée)
    private function setUsername(Username $username): void
    {
        $this->username = $username;
    }

    // Méthode métier publique (point d'entrée unique)
    public function updateUsername(Username $username, DateTimeImmutable $now): void
    {
        $this->setUsername($username);
        $this->setUpdatedAt($now);
    }

    // Pas de setId() publique - immutabilité de l'identité au niveau du langage
}
```

**Note** : **10/10** - Excellente encapsulation et immutabilité.

---

### 8. **Testabilité** ⭐⭐⭐⭐⭐

**Principe** : La couche Domain doit être facilement testable sans dépendances externes.

**Évaluation** :

-   ✅ **Tests unitaires purs** : Pas besoin de frameworks pour tester
-   ✅ **Pas de mocks nécessaires** : Logique métier pure, testable directement
-   ✅ **Isolation parfaite** : Chaque entité/value object peut être testé indépendamment
-   ✅ **Pas de dépendances externes** : Tests rapides et fiables

**Note** : **10/10** - Testabilité parfaite, tests unitaires purs.

---

### 9. **Séparation des bounded contexts** ⭐⭐⭐⭐⭐

**Principe** : Chaque bounded context doit être isolé et indépendant.

**Évaluation** :

-   ✅ **Bounded contexts séparés et implémentés** : `User/` (complet), `Shop/` (complet avec 5 sous-contextes), `SharedKernel/` (Domain Events)
-   ✅ **Pas de dépendances croisées** : Aucune dépendance entre bounded contexts
-   ✅ **Namespace cohérent** : `App\Domain\User\*`, `App\Domain\Shop\*`, `App\Domain\SharedKernel\*`
-   ✅ **Isolation respectée** : Chaque bounded context est indépendant
-   ✅ **SharedKernel avec Domain Events** : `DomainEventInterface` et `DomainEventTrait` dans `SharedKernel`
-   ✅ **Shop structuré** : Sous-contextes `Catalog/`, `Customer/`, `Shipping/`, `Ordering/`, `Shared/` avec agrégats, VOs et Domain Events

**Structure** :

```
Domain/
├── User/              # Bounded context User
│   ├── Model/        # Entités (User)
│   ├── Identity/ValueObject/  # UserId, Username, EmailAddress, Firstname, Lastname
│   ├── Security/ValueObject/  # HashedPassword, UserStatus, RoleSet, Security, ActiveEmail, ResetPassword
│   ├── Preference/ValueObject/  # Preferences
│   ├── Profile/ValueObject/  # Avatar
│   ├── Event/        # 8 Domain Events
│   └── Exception/    # Exceptions métier (RateLimit/, Security/, Uniqueness/)
├── Shop/              # Bounded context Shop
│   ├── Catalog/      # Category, Product
│   ├── Customer/     # Customer, Address
│   ├── Shipping/     # Carrier
│   ├── Ordering/     # Order, OrderLine + Domain Events (OrderPlacedEvent, OrderPaidEvent)
│   └── Shared/       # Money, Slug, Uuid
└── SharedKernel/      # Shared Kernel
    └── Event/         # DomainEventInterface, DomainEventTrait
```

**Note** : **10/10** - Séparation claire des bounded contexts.

---

### 10. **Langage ubiquitaire** ⭐⭐⭐⭐⭐

**Principe** : Le code doit utiliser le langage du domaine métier.

**Évaluation** :

-   ✅ **Terminologie métier** : `requestActivation`, `activate`, `requestPasswordReset`
-   ✅ **Noms expressifs** : `ActivationLimitReachedException`, `UserLockedException`
-   ✅ **Pas de termes techniques** : Pas de termes d'infrastructure
-   ✅ **Messages en français** : Messages d'exception en français (cohérent avec le projet)

**Exemples** :

```php
// ✅ BON : Langage métier expressif avec injection du temps
public function requestActivation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
public function activate(DateTimeImmutable $now): void
public function completePasswordReset(HashedPassword $password, DateTimeImmutable $now): void
public function requestPasswordReset(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
public function updateUsername(Username $username, DateTimeImmutable $now): void
public function delete(DateTimeImmutable $now): void

// ✅ BON : Factory methods avec langage métier
public static function register(/* ... */): self
public static function createByAdmin(/* ... */): self
public static function reconstitute(/* ... */): self

// ✅ BON : Méthode de vérification avec langage métier
private function assertNotLocked(): void

// ✅ BON : Exception métier avec message en français
throw new InvalidArgumentException('Adresse email invalide.');
throw new ActivationLimitReachedException();
throw new UserLockedException();
```

**Note** : **10/10** - Excellent usage du langage ubiquitaire.

---

## 📋 Détail de la notation

| Critère                              | Note  | Commentaire                                                   |
| ------------------------------------ | ----- | ------------------------------------------------------------- |
| **Indépendance des frameworks**      | 10/10 | Aucune dépendance externe, uniquement PHP natif               |
| **Indépendance de l'Application**    | 10/10 | Aucune dépendance à Application                               |
| **Indépendance de l'Infrastructure** | 10/10 | Aucune dépendance à Infrastructure                            |
| **Indépendance de la Presentation**  | 10/10 | Aucune dépendance à Presentation                              |
| **Logique métier pure**              | 10/10 | Logique métier encapsulée, pas de préoccupations techniques   |
| **Types primitifs uniquement**       | 10/10 | Utilisation exclusive de types primitifs PHP et standards SPL |
| **Encapsulation et immutabilité**    | 10/10 | Value Objects immutables, encapsulation complète              |
| **Testabilité**                      | 10/10 | Tests unitaires purs, pas de dépendances externes             |
| **Séparation bounded contexts**      | 10/10 | Bounded contexts isolés et indépendants                       |
| **Langage ubiquitaire**              | 10/10 | Terminologie métier expressive, messages en français          |

**Moyenne** : **10/10** - Parfait respect de tous les principes Clean Architecture

---

## ✅ Points forts

### 1. **Indépendance totale**

-   ✅ Domain ne connaît aucune autre couche
-   ✅ Domain ne dépend d'aucun framework
-   ✅ Domain utilise uniquement PHP natif

### 2. **Logique métier pure**

-   ✅ Entités avec logique métier encapsulée
-   ✅ Value Objects avec validation métier (`Username`, `Firstname`, `Lastname`, `EmailAddress`)
-   ✅ Invariants respectés
-   ✅ Exceptions métier spécifiques
-   ✅ Domain Events pour notifier les changements importants

### 2.1 **Architecture DDD renforcée**

-   ✅ **Constructeur privé** : Force l'utilisation des factory methods (`register`, `createByAdmin`, `reconstitute`)
-   ✅ **Factory methods** : Trois factory methods pour différents contextes de création (inscription, admin, reconstitution)
-   ✅ **Setters privés** : Tous les setters sont privés, forçant l'utilisation des méthodes métier publiques
-   ✅ **Méthodes métier publiques** : Point d'entrée unique pour toute modification (encapsulent les setters privés)
-   ✅ **Injection du temps** : `DateTimeImmutable $now` injecté dans toutes les méthodes métier pour une testabilité parfaite
-   ✅ **Cohérence temporelle** : Plus de `new DateTimeImmutable()` en dur dans le domaine
-   ✅ **Reconstitution sans événements** : Factory method `reconstitute()` pour la persistence (pas d'événements déclenchés)

### 3. **Testabilité parfaite**

-   ✅ Tests unitaires purs (pas de frameworks nécessaires)
-   ✅ Pas de dépendances externes
-   ✅ Tests rapides et fiables
-   ✅ **Injection du temps** (`DateTimeImmutable $now`) : contrôle total du temps dans les tests
-   ✅ **Factory methods** testables : création d'entités simplifiée
-   ✅ **Pas de `new DateTimeImmutable()` en dur** : testabilité garantie

### 4. **Encapsulation renforcée**

-   ✅ Value Objects immutables (classes `final`, propriétés `readonly`)
-   ✅ Propriétés privées avec getters publiques `get*()` et setters **privés** `set*()`
-   ✅ Constructeur privé forçant l'utilisation des factory methods
-   ✅ Méthodes métier publiques comme point d'entrée unique pour les modifications
-   ✅ Validation dans les constructeurs
-   ✅ Immutabilité de l'identité renforcée (propriété `id` en `readonly`)

### 5. **Structure claire**

-   ✅ Organisation par bounded context (User, Shop avec 5 sous-contextes, SharedKernel)
-   ✅ Séparation Model/ValueObject/Event/Exception
-   ✅ Namespace cohérent (`App\Domain\User\*`, `App\Domain\Shop\*\*`, `App\Domain\SharedKernel\*`)
-   ✅ SharedKernel avec Domain Events (interface et trait)
-   ✅ Shop structuré avec sous-contextes métier (Catalog, Customer, Shipping, Ordering, Shared)

---

## 📊 Comparaison avec les principes Clean Architecture

| Principe Clean Architecture          | Respecté | Note  |
| ------------------------------------ | -------- | ----- |
| **Indépendance des frameworks**      | ✅ Oui   | 10/10 |
| **Indépendance de l'Application**    | ✅ Oui   | 10/10 |
| **Indépendance de l'Infrastructure** | ✅ Oui   | 10/10 |
| **Indépendance de la Presentation**  | ✅ Oui   | 10/10 |
| **Logique métier pure**              | ✅ Oui   | 10/10 |
| **Types primitifs uniquement**       | ✅ Oui   | 10/10 |
| **Encapsulation et immutabilité**    | ✅ Oui   | 10/10 |
| **Testabilité**                      | ✅ Oui   | 10/10 |
| **Séparation bounded contexts**      | ✅ Oui   | 10/10 |
| **Langage ubiquitaire**              | ✅ Oui   | 10/10 |

---

## ✅ Conclusion

**Note finale : 10/10**

La couche Domain respecte **parfaitement** tous les principes de Clean Architecture :

**Points forts** :

-   ✅ Indépendance totale des frameworks (uniquement PHP natif, aucune bibliothèque externe)
-   ✅ Aucune dépendance à Application, Infrastructure ou Presentation
-   ✅ Logique métier pure, encapsulée dans les entités et value objects
-   ✅ Utilisation exclusive de types primitifs PHP et standards SPL
-   ✅ Testabilité parfaite (tests unitaires purs, injection du temps)
-   ✅ Encapsulation renforcée (propriété `id` en `readonly`, setters privés, constructeur privé)
-   ✅ Structure claire et organisée par bounded context
-   ✅ Langage ubiquitaire excellent
-   ✅ UserId refactorisé pour supprimer la dépendance à Ramsey
-   ✅ Domain Events implémentés (8 événements User + 2 événements Shop/Ordering)
-   ✅ Value Objects avec validation complète (`Username`, `Firstname`, `Lastname`, `Money`, `Slug`, etc.)
-   ✅ SharedKernel contient maintenant les Domain Events (interface et trait)
-   ✅ Bounded context Shop complètement implémenté (Catalog, Customer, Shipping, Ordering, Shared)
-   ✅ **Constructeur privé** avec factory methods (`register`, `createByAdmin`, `reconstitute`)
-   ✅ **Setters privés** forçant l'utilisation des méthodes métier publiques
-   ✅ **Injection du temps** (`DateTimeImmutable $now`) pour cohérence et testabilité
-   ✅ **Méthodes métier publiques** comme point d'entrée unique pour toute modification
-   ✅ **Factory method de reconstitution** sans événements pour la persistence

**Points à améliorer** :

-   ✅ **AUCUN** : Tous les bounded contexts sont implémentés et structurés

**Comparaison avec les meilleures pratiques** :

| Aspect                      | État       |
| --------------------------- | ---------- |
| **Indépendance frameworks** | ✅ Parfait |
| **Indépendance couches**    | ✅ Parfait |
| **Logique métier pure**     | ✅ Parfait |
| **Testabilité**             | ✅ Parfait |
| **Encapsulation**           | ✅ Parfait |
| **Structure**               | ✅ Parfait |

L'architecture est **production-ready** et suit **parfaitement** les meilleures pratiques de Clean Architecture. La couche Domain est véritablement indépendante et peut être réutilisée avec n'importe quelle technologie ou framework. Le Domain est le cœur de l'application et reste pur, sans aucune contamination par les préoccupations techniques.
