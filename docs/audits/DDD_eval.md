# Évaluation Domain-Driven Design - Couche Domain

## 📊 Note globale : **9.5/10**

---

## 📝 Dernières modifications documentées

**Date de mise à jour** : Décembre 2025

**Changements principaux** :

1. ✅ **Découpage User par sous-domaines internes** : `Identity/`, `Security/`, `Preference/`, `Profile/` + exceptions regroupées (`RateLimit/`, `Uniqueness/`, `Security/`).
2. ✅ **Contexte Shop complété** : sous-contextes `Catalog/`, `Ordering/`, `Shipping/`, `Customer/`, `Shared/` (VOs `Money`, `Slug`, `Uuid`), agrégats `Product`, `Category`, `Order`, `OrderLine`, `Carrier`, `Address` avec factory methods et gestion explicite des timestamps.
3. ✅ **Gestion explicite des timestamps** : Toutes les méthodes métier sensibles reçoivent `DateTimeImmutable $now` et gèrent `updatedAt` (User, Product, Category, Order, Carrier, Address).
4. ✅ **Immutabilité de createdAt** : Pas de setter public, timestamps posés en factory.
5. ✅ **Domain Events en place** : événements User (inscription, activation, reset…) et Shop (`OrderPlacedEvent`, `OrderPaidEvent`) via `DomainEventTrait`.
6. ✅ **Exceptions d'unicité** : `EmailAlreadyUsedException` et `UsernameAlreadyUsedException` dans `Exception/Uniqueness/` pour gérer les violations d'unicité.

**Principe architectural confirmé** : Le domaine reste **totalement indépendant de l'infrastructure**, avec un contrôle explicite de la logique métier et des timestamps.

---

## 🎯 Principes DDD évalués

### 1. **Value Objects (Objets Valeur)** ⭐⭐⭐⭐⭐

**Principe** : Les Value Objects doivent être immutables, encapsulés et représenter des concepts métier sans identité.

**Évaluation** :

-   ✅ **Immutabilité** : Classes `final` et propriétés privées/readonly (User\*, Money, Slug).
-   ✅ **Encapsulation** : Validation dans les constructeurs.
-   ✅ **Méthodes equals()** : Présentes sur les VOs principaux (`UserId`, `Money`, `Slug`…).
-   ✅ **Validation métier** : Validation dans les constructeurs (`Identity\EmailAddress`, `Identity\UserId`, `Security\HashedPassword`, `Shared\Money`).
-   ✅ **Propriétés privées** : Getters en lecture, pas d’identités dans les VOs.

**Exemple** :

```php
// ✅ BON : Value Object bien encapsulé
final class EmailAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Adresse email invalide.');
        }
        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

// ✅ BON : Propriétés privées avec getters
final readonly class ActiveEmail implements JsonSerializable
{
    public function __construct(
        private int $mailSent = 0, // ✅ Propriété privée
        private ?string $token = null,
        // ...
    ) {}

    public function getMailSent(): int
    {
        return $this->mailSent;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
```

**Value Objects clés** :

**User Context** :

-   ✅ `Identity\Username` : Validation 2-20 caractères, non vide
-   ✅ `Identity\Firstname` / `Lastname` : Validation 2-50 caractères, optionnels
-   ✅ `Identity\EmailAddress` : Validation email avec normalisation
-   ✅ `Identity\UserId` : Identifiant unique de l'utilisateur
-   ✅ `Security\HashedPassword` : Mot de passe hashé (pas de getter)
-   ✅ `Security\UserStatus` : Statut utilisateur avec flags binaires (ACTIVE, BLOCKED)
-   ✅ `Security\RoleSet` : Ensemble de rôles utilisateur
-   ✅ `Security\ActiveEmail`, `Security\ResetPassword` : VOs pour la gestion d'activation/réinitialisation
-   ✅ `Preference\Preferences` : Préférences utilisateur (langue)
-   ✅ `Profile\Avatar` : Avatar utilisateur

**Shop Context** :

-   ✅ `Shop\Shared\Money` : Montant en minor units + devise, addition/multiplication avec vérif de devise
-   ✅ `Shop\Shared\Slug` : Pattern slug strict
-   ✅ `Catalog\ProductId`, `Catalog\CategoryId` : Identifiants du catalogue
-   ✅ `Ordering\OrderId`, `Ordering\OrderLineId`, `Ordering\OrderReference` : Identifiants et référence de commande
-   ✅ `Ordering\PaymentSessionId`, `Ordering\CarrierSelection`, `Ordering\DeliveryAddress` : VOs de commande
-   ✅ `Shipping\CarrierId` : Identifiant transporteur
-   ✅ `Customer\AddressId` : Identifiant adresse

**Note** : **10/10** - Excellente structure avec encapsulation complète et validation dans tous les Value Objects.

---

### 2. **Entities (Entités)** ⭐⭐⭐⭐⭐

**Principe** : Les Entities ont une identité et encapsulent la logique métier.

**Évaluation** :

-   ✅ **Identité** : `UserId` comme identifiant unique
-   ✅ **Logique métier encapsulée** : Méthodes métier expressives (`requestActivation`, `activate`, `requestPasswordReset`)
-   ✅ **Factory methods** : `register()` et `createByAdmin()` pour créer l'entité
-   ✅ **Domain Events** : Événements émis pour les actions importantes (`UserRegisteredEvent`, `UserActivatedEvent`, etc.)
-   ✅ **Invariants respectés** : Vérification des limites de tokens, vérification de verrouillage
-   ✅ **Encapsulation** : Propriétés privées avec getters `get*()` et setters `set*()`
-   ✅ **Immutabilité de l'identité** : Propriété `id` en `readonly`, pas de méthode `setId()` publique, utilisation de la réflexion uniquement dans le repository
-   ✅ **Gestion explicite de updatedAt** : Les méthodes métier gèrent `updatedAt` via un paramètre `DateTimeImmutable $now`
-   ✅ **Validation complète** : Toutes les propriétés avec validation métier utilisent des Value Objects (`Username`, `Firstname`, `Lastname`, `EmailAddress`)
-   ✅ **Méthode equals()** : Présente pour comparer deux instances basées sur l'identité

**Exemples** :

```php
// ✅ BON : Constructeur privé (force l'utilisation des factory methods)
private function __construct(
    private readonly ?UserId $id,
    private Username $username,
    private ?Firstname $firstname,
    // ...
) {}

// ✅ BON : Factory method avec Domain Event
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

// ✅ BON : Factory method de reconstitution (sans événements)
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

// ✅ BON : Logique métier encapsulée avec Domain Event
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

// ✅ BON : Méthode equals() pour comparer deux instances
public function equals(self $other): bool
{
    if (null === $this->id || null === $other->id) {
        return false;
    }
    return $this->id->equals($other->id);
}

// ✅ BON : Getters publiques (lecture)
public function getId(): ?UserId
{
    return $this->id;
}

// ✅ BON : Setters privés (modification contrôlée)
private function setUsername(Username $username): void
{
    $this->username = $username;
}

// ✅ BON : Méthodes métier publiques (point d'entrée pour les modifications)
public function updateUsername(Username $username, DateTimeImmutable $now): void
{
    $this->setUsername($username);
    $this->setUpdatedAt($now); // Gestion explicite de updatedAt
}
```

**Note** : **10/10** - Excellente encapsulation de la logique métier, immutabilité de l'identité respectée (propriété `readonly`), gestion explicite des timestamps au niveau du domaine, validation complète via Value Objects, et Domain Events implémentés.

#### Architecture de l'entité User

L'entité `User` suit une architecture rigoureuse basée sur les principes DDD :

##### 1. Constructeur privé

Le constructeur est **privé** pour garantir l'intégrité de l'agrégat. Cette approche offre plusieurs avantages :

1. **Force l'utilisation des factory methods** (`register`, `createByAdmin`, `reconstitute`)
2. **Garantit que toute création passe par la logique métier appropriée**
3. **Assure que les événements domaine sont toujours déclenchés** lors de la création
4. **Empêche la création d'entités dans un état incohérent**
5. **Rend explicites les différents contextes de création** (inscription, admin, reconstitution)

Les factory methods publiques encapsulent la logique de création et documentent clairement l'intention métier, contrairement à un constructeur public générique.

##### 2. Factory methods statiques

Trois factory methods permettent de créer des instances de `User` selon le contexte :

**`register()`** : Création lors de l'inscription

-   Factory method statique pour créer un utilisateur lors de l'inscription
-   Initialise l'utilisateur avec le rôle `ROLE_USER` et le statut `INACTIVE`
-   Déclenche l'événement `UserRegisteredEvent`
-   Méthode statique car elle crée une nouvelle instance (pattern factory)

**`createByAdmin()`** : Création par un administrateur

-   Factory method statique pour créer un utilisateur par un administrateur
-   Permet de définir les rôles et le statut dès la création
-   Déclenche l'événement `UserCreatedByAdminEvent`
-   Offre plus de flexibilité que `register()` pour les cas d'usage administratifs

**`reconstitute()`** : Reconstitution depuis la persistance

-   Factory method pour reconstituer un utilisateur depuis la persistence
-   **Ne déclenche aucun événement domaine** car l'entité existe déjà
-   Utilisé **uniquement par la couche infrastructure** (Mapper)
-   Permet de recréer l'entité sans effets de bord

##### 3. Encapsulation via getters/setters

**Getters publiques** : Accès en lecture aux propriétés

-   Tous les getters sont publiques pour permettre la lecture de l'état
-   Convention de nommage `get*()` respectée
-   Retournent des Value Objects pour garantir l'immutabilité

**Setters privés** : Modification contrôlée

-   Tous les setters sont **privés** pour garantir que toute modification passe par les méthodes métier
-   Cela préserve l'encapsulation et assure que la logique métier et les événements sont toujours déclenchés de manière cohérente
-   Note : Il n'existe pas de `setCreatedAt()` car `createdAt` est immuable après la création
-   Le setter `setUpdatedAt()` est privé et appelé uniquement par les méthodes métier qui modifient l'état

**Méthodes métier publiques** : Point d'entrée unique pour les modifications

-   Toutes les modifications passent par des méthodes métier expressives (`activate()`, `updateUsername()`, etc.)
-   Ces méthodes encapsulent la logique, les validations et le déclenchement des événements
-   Chaque méthode reçoit un paramètre `DateTimeImmutable $now` pour gérer explicitement `updatedAt`
-   Garantit la cohérence, la traçabilité des changements et l'indépendance vis-à-vis de l'infrastructure

##### 4. Méthodes d'instance vs méthodes statiques

-   **Méthodes statiques** (`register`, `createByAdmin`, `reconstitute`) : Créent une nouvelle instance
-   **Méthodes d'instance** (`activate`, `delete`, `updateUsername`, etc.) : Modifient un `User` existant
-   Cette distinction claire facilite la compréhension du cycle de vie de l'entité

##### 5. Gestion des timestamps

L'entité `User` gère les timestamps (`createdAt`, `updatedAt`) de manière explicite au niveau du domaine :

**`createdAt` - Immuable après création**

-   Défini uniquement lors de la création via les factory methods (`register`, `createByAdmin`)
-   Pas de setter `setCreatedAt()` pour garantir l'immutabilité
-   Getter public `getCreatedAt()` pour la lecture

**`updatedAt` - Gestion explicite dans les méthodes métier**

-   Chaque méthode métier qui modifie l'état reçoit un paramètre `DateTimeImmutable $now`
-   Les méthodes appellent explicitement `setUpdatedAt($now)` après modification
-   Setter privé `setUpdatedAt()` pour garantir le contrôle
-   Getter public `getUpdatedAt()` pour la lecture

**Avantages de cette approche** :

-   ✅ **Indépendance du domaine** : Aucune dépendance à l'infrastructure (Doctrine, Gedmo)
-   ✅ **Testabilité** : Les tests peuvent injecter facilement des dates spécifiques
-   ✅ **Contrôle total** : Le domaine contrôle explicitement quand `updatedAt` est modifié
-   ✅ **Traçabilité** : Chaque modification est tracée au niveau du domaine
-   ✅ **Clarté** : L'intention métier est explicite dans chaque méthode

---

### 3. **Aggregates (Agrégats)** ⭐⭐⭐⭐

**Principe** : Les Aggregates sont des clusters d'entités et de Value Objects avec une racine d'agrégat.

**Évaluation** :

-   ✅ **Racines d'agrégat** :
    -   `User` (User Context) : 17 propriétés, taille acceptable et justifiée
    -   `Order` (Shop Context) : Agrégat racine avec `OrderLine` comme entité enfant
    -   `Product`, `Category` (Shop Context) : Entités avec factory methods
-   ✅ **Encapsulation** : Tous les Value Objects sont accessibles uniquement via la racine
-   ✅ **Invariants** : Respect des invariants métier
    -   User : limite de tokens, verrouillage
    -   Order : cohérence des devises, calculs de montants
-   ✅ **Références entre contextes** : Shop utilise `UserId` de User (référence légitime entre contextes)
-   ✅ **Domain Events** : Événements émis pour les actions importantes (User : 8 événements, Shop : 2 événements)
-   ✅ **Pas de navigation vers d'autres agrégats** : Bonne isolation, références uniquement par ID
-   ✅ **Factory methods** : Tous les agrégats utilisent des factory methods (`create()`, `register()`, `place()`, `reconstitute()`)

**Note** : **9.5/10** - Excellente structure d'agrégats avec Domain Events, factory methods et gestion explicite des timestamps. Taille acceptable pour tous les agrégats.

---

### 4. **Domain Exceptions (Exceptions Métier)** ⭐⭐⭐⭐⭐

**Principe** : Les exceptions métier doivent être spécifiques et expressives.

**Évaluation** :

-   ✅ **Hiérarchie d'exceptions** : `UserDomainException` comme base
-   ✅ **Organisation par catégories** : Exceptions regroupées par sous-domaines (`RateLimit/`, `Security/`, `Uniqueness/`)
-   ✅ **Exceptions spécifiques** :
    -   `RateLimit/` : `ActivationLimitReachedException`, `ResetPasswordLimitReachedException`
    -   `Security/` : `UserLockedException`
    -   `Uniqueness/` : `EmailAlreadyUsedException`, `UsernameAlreadyUsedException`
-   ✅ **Messages explicites** : Toutes les exceptions ont des messages en français clairs
-   ✅ **Exceptions problématiques supprimées** : `ActivationTokenException` et `ResetPasswordTokenException` ont été supprimées

**Exemple** :

```php
// ✅ BON : Exception métier spécifique avec message (RateLimit)
final class ActivationLimitReachedException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('Nombre maximal d\'emails d\'activation atteint.');
    }
}

// ✅ BON : Exception métier avec message (Security)
final class UserLockedException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('Le compte est verrouillé.');
    }
}

// ✅ BON : Exception métier avec message (RateLimit)
final class ResetPasswordLimitReachedException extends UserDomainException
{
    public function __construct()
    {
        parent::__construct('Nombre maximal d\'emails de réinitialisation atteint.');
    }
}

// ✅ BON : Exception métier avec message (Uniqueness)
final class EmailAlreadyUsedException extends UserDomainException
{
    public function __construct(string $message = 'Adresse email déjà utilisée.')
    {
        parent::__construct($message);
    }
}

// ✅ BON : Exception métier avec message (Uniqueness)
final class UsernameAlreadyUsedException extends UserDomainException
{
    public function __construct(string $message = 'Nom d\'utilisateur déjà utilisé.')
    {
        parent::__construct($message);
    }
}
```

**Note** : **10/10** - Excellente hiérarchie avec organisation par catégories, toutes les exceptions ont des messages explicites.

---

### 5. **Bounded Contexts (Contextes Délimités)** ⭐⭐⭐⭐

**Principe** : Chaque Bounded Context doit être isolé et avoir son propre modèle métier.

**Évaluation** :

-   ✅ **Séparation claire** : `User/`, `Shop/`, `SharedKernel/`.
-   ✅ **User** : Sous-domaines internes (`Identity/`, `Security/`, `Preference/`, `Profile/`, exceptions groupées).
-   ✅ **Shop** : Sous-contextes métier (`Catalog/`, `Ordering/`, `Shipping/`, `Customer/`, `Shared/` pour Money/Slug/UUID).
-   ✅ **Pas de dépendances croisées interdites** : User ne dépend pas de Shop ; Shop ne dépend pas d’Application/Infra/Presentation.
-   ✅ **SharedKernel** : Domain events communs.

**Note** : **8.5/10** - Bounded contexts explicites et renseignés.

---

### 6. **Ubiquitous Language (Langage Ubiquitaire)** ⭐⭐⭐⭐⭐

**Principe** : Le code doit utiliser le langage du domaine métier.

**Évaluation** :

-   ✅ **Terminologie métier** : `requestActivation`, `activeEmail`, `resetPassword`
-   ✅ **Noms expressifs** : `ActivationLimitReachedException`, `UserLockedException`
-   ✅ **Pas de termes techniques** : Pas de termes d'infrastructure dans le domaine
-   ✅ **Messages en français** : Messages d'exception en français

**Méthodes métier disponibles** :

```php
// ✅ BON : Langage métier expressif et complet
// Gestion de l'activation
public function requestActivation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
public function activate(DateTimeImmutable $now): void
public function clearActivation(): void

// Gestion du mot de passe
public function requestPasswordReset(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
public function completePasswordReset(HashedPassword $password, DateTimeImmutable $now): void
public function changePassword(HashedPassword $password, DateTimeImmutable $now): void

// Mises à jour
public function updateUsername(Username $username, DateTimeImmutable $now): void
public function updateEmail(EmailAddress $email, DateTimeImmutable $now): void
public function updateFirstname(?Firstname $firstname, DateTimeImmutable $now): void
public function updateLastname(?Lastname $lastname, DateTimeImmutable $now): void
public function updateAvatar(Avatar $avatar, DateTimeImmutable $now): void
public function updateRoles(RoleSet $roles, DateTimeImmutable $now): void
public function updateStatus(UserStatus $status, DateTimeImmutable $now): void
public function updateByAdmin(DateTimeImmutable $now, /* paramètres optionnels */): void

// Suppression
public function delete(DateTimeImmutable $now): void

// Vérifications
public function assertNotLocked(): void
public function isActive(): bool
public function isLocked(): bool
```

**Note** : **10/10** - Excellent usage du langage ubiquitaire.

---

### 7. **Encapsulation (Encapsulation)** ⭐⭐⭐⭐

**Principe** : Les détails d'implémentation doivent être cachés.

**Évaluation** :

-   ✅ **Propriétés privées** : Entité `User` avec propriétés privées
-   ✅ **Getters** : Accès via méthodes getters
-   ✅ **Propriétés privées dans Value Objects** : Tous les Value Objects ont des propriétés privées avec getters `get*()`
-   ✅ **Méthodes métier** : Logique métier encapsulée dans l'entité

**Note** : **9/10** - Excellente encapsulation dans l'entité et les Value Objects.

---

### 8. **Invariants (Invariants Métier)** ⭐⭐⭐⭐⭐

**Principe** : Les invariants métier doivent être respectés à tout moment.

**Évaluation** :

-   ✅ **Limite de tokens** : Vérification de `MAX_TOKEN_REQUESTS` dans `requestActivation` et `requestPasswordReset`
-   ✅ **Vérification de verrouillage** : `assertNotLocked()` avant certaines opérations
-   ✅ **Validation dans Value Objects** : Validation dans les constructeurs
-   ✅ **Invariants respectés** : Les invariants sont vérifiés avant les modifications

**Exemple** :

```php
// ✅ BON : Invariant respecté (limite de tokens)
public function requestActivation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
{
    if ($this->activeEmail->getMailSent() >= self::MAX_TOKEN_REQUESTS) {
        throw new ActivationLimitReachedException();
    }
    // ...
}

// ✅ BON : Invariant respecté (vérification de verrouillage)
public function requestPasswordReset(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
{
    $this->assertNotLocked();
    // ...
}
```

**Note** : **10/10** - Excellente gestion des invariants métier.

---

### 9. **Immutability (Immutabilité)** ⭐⭐⭐⭐

**Principe** : Les Value Objects doivent être immutables.

**Évaluation** :

-   ✅ **Classes final** : Toutes les classes sont `final`
-   ✅ **Propriétés readonly** : Utilisation de `readonly` pour certains Value Objects
-   ✅ **Méthodes with\*** : Méthodes `with*` pour créer de nouvelles instances
-   ✅ **Propriétés privées** : Tous les Value Objects ont des propriétés privées avec getters `get*()`

**Exemple** :

```php
// ✅ BON : Value Object immuable avec méthodes with* et encapsulation
final readonly class ResetPassword implements JsonSerializable
{
    public function __construct(
        private int $mailSent = 0, // ✅ Propriété privée
        private ?string $token = null,
        private ?int $tokenTtl = null,
    ) {}

    public function getMailSent(): int
    {
        return $this->mailSent;
    }

    public function withMailSent(int $mailSent): self
    {
        return new self(
            mailSent: $mailSent,
            token: $this->token,
            tokenTtl: $this->tokenTtl,
        );
    }
}
```

**Note** : **9/10** - Excellente immutabilité avec encapsulation complète.

---

### 10. **Domain Logic (Logique Métier)** ⭐⭐⭐⭐⭐

**Principe** : Toute la logique métier doit être dans le domaine.

**Évaluation** :

-   ✅ **Pas de dépendances infrastructure** : Aucune dépendance à Doctrine, Symfony, etc.
-   ✅ **Logique métier encapsulée** : Toute la logique dans l'entité `User`
-   ✅ **Pas de services externes** : Pas de dépendance à des services d'infrastructure
-   ✅ **Pure logique métier** : Seulement de la logique métier pure

**Note** : **10/10** - Excellente isolation de la logique métier.

---

### 11. **Tests Unitaires** ⭐⭐⭐⭐

**Principe** : Le domaine doit être testable unitairement.

**Évaluation** :

-   ✅ **Tests présents** : Tests unitaires pour `User`
-   ✅ **Tests d'invariants** : Tests des limites et vérifications
-   ✅ **Isolation** : Tests isolés sans dépendances
-   ⚠️ **Utilisation de Reflection** : Utilisation de Reflection pour tester des cas limites (acceptable mais pas idéal)

**Exemple** :

```php
// ✅ BON : Test d'invariant
public function testRequestActivationThrowsWhenLimitReached(): void
{
    $user = $this->createUser();
    $this->setActiveEmail($user, new ActiveEmail(mailSent: 3));

    $this->expectException(ActivationLimitReachedException::class);

    $user->requestActivation('token', new DateTimeImmutable('+1 day'));
}
```

**Note** : **8/10** - Bonne couverture de tests, mais utilisation de Reflection.

---

## ⚠️ Points d'amélioration critiques

### 1. **UserStatus - Flags binaires incorrects** ✅ **CORRIGÉ**

**Problème** : Les constantes `ACTIVE = 3` et `BLOCKED = 4` n'étaient pas des puissances de 2, ce qui cassait les opérations bitwise.

**Solution appliquée** :

-   ✅ Constantes corrigées pour utiliser des puissances de 2
-   ✅ `INACTIVE = 0` (inchangé)
-   ✅ `ACTIVE = 1` (au lieu de 3)
-   ✅ `BLOCKED = 2` (au lieu de 4)
-   ✅ Les opérations bitwise `|` et `&` fonctionnent maintenant correctement
-   ✅ `addFlag()` et `hasFlag()` produisent des résultats corrects

**Code corrigé** :

```php
public const int INACTIVE = 0;
public const int ACTIVE = 1;      // ✅ Puissance de 2
public const int BLOCKED = 2;     // ✅ Puissance de 2

public function addFlag(int $flag): self
{
    return new self($this->value | $flag); // ✅ Fonctionne correctement maintenant
}
```

**Note** : ✅ **CORRIGÉ** - Les flags binaires utilisent maintenant des puissances de 2.

---

### 2. **Encapsulation des Value Objects** ✅ **CORRIGÉ**

**Problème** : Plusieurs Value Objects exposaient des propriétés publiques.

**Solution appliquée** :

-   ✅ Propriétés rendues privées dans tous les Value Objects
-   ✅ Getters `get*()` ajoutés pour chaque propriété
-   ✅ Méthodes `with*` conservées pour l'immuabilité
-   ✅ Usages mis à jour dans `User.php` et `UserTest.php`

**Value Objects corrigés** :

-   ✅ `ActiveEmail` : Propriétés privées + getters `getMailSent()`, `getToken()`, `getTokenTtl()`, `getLastAttempt()`
-   ✅ `ResetPassword` : Propriétés privées + getters `getMailSent()`, `getToken()`, `getTokenTtl()`
-   ✅ `Security` : Propriétés privées + getters `getTotalWrongPassword()`, `getTotalWrongTwoFactorCode()`, `getTotalTwoFactorSmsSent()`
-   ✅ `Preferences` : Propriété privée + getter `getLang()`

**Note** : ✅ **CORRIGÉ** - Encapsulation complète respectée.

---

### 3. **setId() problématique** ✅ **CORRIGÉ**

**Problème** : La méthode `setId()` permettait de changer l'ID après création.

**Solution appliquée** :

-   ✅ Propriété `id` rendue `readonly` dans le constructeur
-   ✅ Méthode `setId()` supprimée de l'entité `User`
-   ✅ Utilisation de la réflexion dans le repository pour définir l'ID après la persistance
-   ✅ L'ID ne peut plus être modifié publiquement après création
-   ✅ Immutabilité de l'identité renforcée au niveau du langage

**Code corrigé** :

```php
// ✅ BON : Propriété id en readonly, pas de méthode setId() publique
public function __construct(
    private readonly ?UserId $id,  // ✅ readonly pour garantir l'immutabilité
    // ...
) {}

public function getId(): ?UserId
{
    return $this->id;
}

// L'ID est défini uniquement via la réflexion dans le repository (infrastructure)
// lors de la persistance, garantissant l'immutabilité de l'identité au niveau du langage
```

**Note** : ✅ **CORRIGÉ** - L'immutabilité de l'identité est maintenant respectée au niveau du langage.

---

### 4. **Gestion de updatedAt** ✅ **IMPLÉMENTÉ**

**Approche** : Les méthodes métier gèrent explicitement `updatedAt` pour un contrôle total au niveau du domaine.

**Solution appliquée** :

-   ✅ Chaque méthode métier reçoit un paramètre `DateTimeImmutable $now`
-   ✅ Les méthodes appellent explicitement `setUpdatedAt($now)` après modification
-   ✅ Contrôle total au niveau du domaine (pas de dépendance à l'infrastructure)
-   ✅ Getters publiques `getCreatedAt()` et `getUpdatedAt()` pour la cohérence
-   ✅ Setter privé `setUpdatedAt()` pour modification contrôlée
-   ✅ Pas de `setCreatedAt()` car `createdAt` est immuable après la création

**Code implémenté** :

```php
// ✅ BON : Gestion explicite de updatedAt dans les méthodes métier
public function updateUsername(Username $username, DateTimeImmutable $now): void
{
    $this->setUsername($username);
    $this->setUpdatedAt($now);
}

public function activate(DateTimeImmutable $now): void
{
    $this->setStatus($this->getStatus()->addFlag(UserStatus::ACTIVE));
    $this->clearActivation();
    $this->setUpdatedAt($now);
    // ...
}

public function getCreatedAt(): DateTimeImmutable
{
    return $this->createdAt;
}

public function getUpdatedAt(): DateTimeImmutable
{
    return $this->updatedAt;
}

private function setUpdatedAt(DateTimeImmutable $updatedAt): void
{
    $this->updatedAt = $updatedAt;
}
```

**Note** : ✅ **IMPLÉMENTÉ** - Gestion explicite et contrôlée de `updatedAt` au niveau du domaine, garantissant l'indépendance vis-à-vis de l'infrastructure.

---

### 5. **Validation manquante** ✅ **CORRIGÉ**

**Problème** : Les méthodes `update*` n'avaient pas de validation.

**Solution appliquée** :

-   ✅ Création de Value Objects `Username`, `Firstname`, `Lastname` avec validation dans les constructeurs
-   ✅ Validation automatique lors de la création des Value Objects
-   ✅ Les méthodes `update*` utilisent maintenant les Value Objects, garantissant la validation
-   ✅ Cohérence avec le pattern existant (`EmailAddress`)

**Value Objects créés** :

-   ✅ `Username` : Validation 2-20 caractères, non vide, trim automatique
-   ✅ `Firstname` : Validation 2-50 caractères, optionnel, trim automatique
-   ✅ `Lastname` : Validation 2-50 caractères, optionnel, trim automatique

**Code corrigé** :

```php
// ✅ BON : Validation via Value Object
public function updateUsername(Username $username): void
{
    $this->setUsername($username); // Validation déjà faite dans le constructeur de Username
}

// ✅ BON : Value Object avec validation
final class Username
{
    private const int MIN_LENGTH = 2;
    private const int MAX_LENGTH = 20;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (empty($trimmed)) {
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

**Note** : ✅ **CORRIGÉ** - Validation complète via Value Objects, suivant les meilleures pratiques DDD.

---

### 6. **Bounded context Shop complété** ✅ **FAIT**

**Problème initial** : `Shop/` était vide, structure confuse.

**Solution appliquée** :

-   ✅ Sous-contextes créés et remplis :
    -   `Catalog/` : `Product` et `Category` avec factory methods (`create()`, `reconstitute()`) et gestion explicite des timestamps
    -   `Ordering/` : `Order` (agrégat racine), `OrderLine` (entité), Domain Events (`OrderPlacedEvent`, `OrderPaidEvent`)
    -   `Shipping/` : `Carrier` avec prix via `Money`
    -   `Customer/` : `Address` lié à `UserId`
    -   `Shared/` : VOs partagés (`Money`, `Slug`, `Uuid`)
-   ✅ Invariants de devise/quantité adressés dans `Order`.
-   ✅ Factory methods et reconstitution pour tous les agrégats Shop.
-   ✅ Gestion explicite des timestamps (`DateTimeImmutable $now`) dans toutes les méthodes métier.
-   ✅ Événements et VOs dédiés, sans dépendance framework.

---

### 7. **Absence de Domain Events** ✅ **CORRIGÉ**

**Problème** : Pas de mécanisme d'événements pour notifier les changements importants.

**Solution appliquée** :

-   ✅ Implémentation de `DomainEventTrait` dans `SharedKernel`
-   ✅ Utilisation de `recordEvent()` dans les agrégats (`User`, `Order`)
-   ✅ Création de 10 Domain Events au total pour les actions importantes
-   ✅ Événements émis lors des factory methods et des actions métier

**Domain Events créés** :

**User Context (8 événements)** :

-   ✅ `UserRegisteredEvent` : Émis lors de l'inscription
-   ✅ `UserCreatedByAdminEvent` : Émis lors de la création par un admin
-   ✅ `UserActivatedEvent` : Émis lors de l'activation
-   ✅ `ActivationEmailRequestedEvent` : Émis lors de la demande d'activation
-   ✅ `PasswordResetRequestedEvent` : Émis lors de la demande de réinitialisation
-   ✅ `PasswordResetCompletedEvent` : Émis lors de la complétion de réinitialisation
-   ✅ `UserUpdatedByAdminEvent` : Émis lors de la mise à jour par un admin
-   ✅ `UserDeletedEvent` : Émis lors de la suppression

**Shop Context (2 événements)** :

-   ✅ `OrderPlacedEvent` : Émis lors de la création d'une commande
-   ✅ `OrderPaidEvent` : Émis lors du paiement d'une commande

**Code implémenté** :

```php
// ✅ BON : Domain Event émis lors de la création
public static function register(...): self {
    $user = new self(...);

    $user->recordEvent(new UserRegisteredEvent(
        userId: $id,
        email: $email,
        occurredOn: $now,
    ));

    return $user;
}

// ✅ BON : Domain Event émis lors d'une action métier
public function activate(DateTimeImmutable $now): void
{
    $this->setStatus($this->getStatus()->addFlag(UserStatus::ACTIVE));
    $this->clearActivation();
    $this->setUpdatedAt($now);

    if (null !== $this->id) {
        $this->recordEvent(new UserActivatedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
    }
}
```

**Note** : ✅ **CORRIGÉ** - Domain Events implémentés (10 événements au total : 8 User + 2 Shop), permettant le découplage et la notification des changements importants.

---

## 📋 Détail de la notation

| Critère                 | Note   | Commentaire                                                                                                       |
| ----------------------- | ------ | ----------------------------------------------------------------------------------------------------------------- |
| **Value Objects**       | 10/10  | Excellente structure avec encapsulation complète et validation                                                    |
| **Entities**            | 10/10  | Excellente logique métier, immutabilité de l'identité, validation complète                                        |
| **Aggregates**          | 9.5/10 | Excellente structure avec Domain Events, factory methods et gestion explicite des timestamps                      |
| **Domain Exceptions**   | 10/10  | Excellente hiérarchie avec organisation par catégories, toutes les exceptions ont des messages explicites         |
| **Bounded Contexts**    | 9.5/10 | Séparation claire User/Shop/SharedKernel, Shop complet avec agrégats (Product, Order, Category, Carrier, Address) |
| **Ubiquitous Language** | 10/10  | Excellent usage du langage métier                                                                                 |
| **Encapsulation**       | 9/10   | Excellente encapsulation dans l'entité et les Value Objects                                                       |
| **Invariants**          | 10/10  | Excellente gestion des invariants                                                                                 |
| **Immutability**        | 9/10   | Excellente immutabilité avec encapsulation complète                                                               |
| **Domain Logic**        | 10/10  | Excellente isolation de la logique métier                                                                         |
| **Tests Unitaires**     | 8/10   | Bonne couverture, mais utilisation de Reflection                                                                  |

**Moyenne** : **9.5/10**

---

## 🎯 Structure du domaine

### Organisation

```
domain/
├── User/                          # Bounded context User
│   ├── src/
│   │   ├── Model/                # Entités (Agrégats)
│   │   │   └── User.php
│   │   ├── Event/                # Domain Events
│   │   │   ├── UserRegisteredEvent.php
│   │   │   ├── UserCreatedByAdminEvent.php
│   │   │   ├── UserActivatedEvent.php
│   │   │   ├── ActivationEmailRequestedEvent.php
│   │   │   ├── PasswordResetRequestedEvent.php
│   │   │   ├── PasswordResetCompletedEvent.php
│   │   │   ├── UserUpdatedByAdminEvent.php
│   │   │   └── UserDeletedEvent.php
│   │   ├── ValueObject/          # Value Objects
│   │   │   ├── UserId.php
│   │   │   ├── Username.php
│   │   │   ├── Firstname.php
│   │   │   ├── Lastname.php
│   │   │   ├── EmailAddress.php
│   │   │   ├── HashedPassword.php
│   │   │   ├── UserStatus.php
│   │   │   ├── RoleSet.php
│   │   │   ├── ActiveEmail.php
│   │   │   ├── ResetPassword.php
│   │   │   ├── Security.php
│   │   │   ├── Preferences.php
│   │   │   └── Avatar.php
│   │   └── Exception/             # Exceptions métier
│   │       ├── UserDomainException.php
│   │       ├── RateLimit/
│   │       │   ├── ActivationLimitReachedException.php
│   │       │   └── ResetPasswordLimitReachedException.php
│   │       ├── Security/
│   │       │   └── UserLockedException.php
│   │       └── Uniqueness/
│   │           ├── EmailAlreadyUsedException.php
│   │           └── UsernameAlreadyUsedException.php
│   └── tests/
│       └── Unit/
│           └── UserTest.php
│
├── Shop/                          # Bounded context Shop
│   ├── src/
│   │   ├── Catalog/
│   │   │   ├── Model/
│   │   │   │   ├── Product.php
│   │   │   │   └── Category.php
│   │   │   └── ValueObject/
│   │   │       ├── ProductId.php
│   │   │       └── CategoryId.php
│   │   ├── Ordering/
│   │   │   ├── Model/
│   │   │   │   ├── Order.php
│   │   │   │   └── OrderLine.php
│   │   │   ├── Event/
│   │   │   │   ├── OrderPlacedEvent.php
│   │   │   │   └── OrderPaidEvent.php
│   │   │   └── ValueObject/
│   │   │       ├── OrderId.php
│   │   │       ├── OrderLineId.php
│   │   │       ├── OrderReference.php
│   │   │       ├── PaymentSessionId.php
│   │   │       ├── CarrierSelection.php
│   │   │       └── DeliveryAddress.php
│   │   ├── Shipping/
│   │   │   ├── Model/
│   │   │   │   └── Carrier.php
│   │   │   └── ValueObject/
│   │   │       └── CarrierId.php
│   │   ├── Customer/
│   │   │   ├── Model/
│   │   │   │   └── Address.php
│   │   │   └── ValueObject/
│   │   │       └── AddressId.php
│   │   └── Shared/
│   │       └── ValueObject/
│   │           ├── Money.php
│   │           ├── Slug.php
│   │           └── Uuid.php
│
└── SharedKernel/                  # Shared Kernel
    └── src/
        └── Event/
            ├── DomainEventInterface.php
            └── DomainEventTrait.php
```

### Flux de dépendances

```
┌─────────────────────────────────────────────────────────┐
│                    Domain                                │
│                                                         │
│  ┌──────────────────────────────────────┐             │
│  │  Bounded Context User                 │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Aggregate Root                  │ │             │
│  │  │  - User (Entity)                 │ │             │
│  │  └────────────────────────────────┘ │             │
│  │         │                             │             │
│  │         │ contient                    │             │
│  │         ▼                             │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Value Objects                  │ │             │
│  │  │  - UserId                       │ │             │
│  │  │  - Username                     │ │             │
│  │  │  - Firstname                    │ │             │
│  │  │  - Lastname                     │ │             │
│  │  │  - EmailAddress                 │ │             │
│  │  │  - HashedPassword               │ │             │
│  │  │  - UserStatus                   │ │             │
│  │  │  - RoleSet                      │ │             │
│  │  │  - ActiveEmail                  │ │             │
│  │  │  - ResetPassword                │ │             │
│  │  │  - Security                     │ │             │
│  │  │  - Preferences                  │ │             │
│  │  │  - Avatar                       │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Domain Events                   │ │             │
│  │  │  - UserRegisteredEvent           │ │             │
│  │  │  - UserActivatedEvent             │ │             │
│  │  │  - PasswordResetRequestedEvent   │ │             │
│  │  │  - UserDeletedEvent               │ │             │
│  │  │  - ... (8 événements au total)    │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Domain Exceptions               │ │             │
│  │  │  - UserDomainException           │ │             │
│  │  │  - ActivationLimitReached...    │ │             │
│  │  │  - UserLockedException          │ │             │
│  │  └────────────────────────────────┘ │             │
│  └──────────────────────────────────────┘             │
│                                                         │
│  ┌──────────────────────────────────────┐             │
│  │  Bounded Context Shop                 │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Catalog                        │ │             │
│  │  │  - Product (Aggregate)          │ │             │
│  │  │  - Category (Entity)            │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Ordering                       │ │             │
│  │  │  - Order (Aggregate Root)       │ │             │
│  │  │  - OrderLine (Entity)           │ │             │
│  │  │  - OrderPlacedEvent             │ │             │
│  │  │  - OrderPaidEvent               │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Shipping                       │ │             │
│  │  │  - Carrier (Entity)             │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Customer                       │ │             │
│  │  │  - Address (Entity)             │ │             │
│  │  └────────────────────────────────┘ │             │
│  │                                       │             │
│  │  ┌────────────────────────────────┐ │             │
│  │  │  Shared                        │ │             │
│  │  │  - Money (VO)                  │ │             │
│  │  │  - Slug (VO)                   │ │             │
│  │  └────────────────────────────────┘ │             │
│  └──────────────────────────────────────┘             │
│                                                         │
│  ┌──────────────────────────────────────┐             │
│  │  Shared Kernel                        │             │
│  │  - DomainEventInterface                │             │
│  │  - DomainEventTrait                    │             │
│  └──────────────────────────────────────┘             │
│                                                         │
│  Aucune dépendance vers Infrastructure ou Framework   │
└─────────────────────────────────────────────────────────┘
```

---

## ✅ Points forts

### 1. **Excellente logique métier**

-   ✅ Logique métier bien encapsulée dans les agrégats (`User`, `Order`, `Product`, `Category`, `Carrier`, `Address`)
-   ✅ Méthodes métier expressives et claires
-   ✅ Invariants respectés
    -   User : limite de tokens, verrouillage
    -   Order : cohérence des devises, calculs de montants
-   ✅ Factory methods (`register()`, `createByAdmin()`, `create()`, `place()`) pour créer les agrégats
-   ✅ Domain Events pour notifier les changements importants (10 événements au total)

### 2. **Value Objects bien structurés**

-   ✅ Validation dans les constructeurs
-   ✅ Méthodes `equals()` présentes sur les Value Objects et l'entité `User`
-   ✅ Classes `final` pour l'immutabilité
-   ✅ Méthodes `with*` pour créer de nouvelles instances

### 3. **Isolation du domaine**

-   ✅ Aucune dépendance à l'infrastructure
-   ✅ Aucune dépendance aux frameworks
-   ✅ Pure logique métier

### 4. **Langage ubiquitaire**

-   ✅ Terminologie métier expressive
-   ✅ Noms de méthodes clairs
-   ✅ Messages en français

### 5. **Gestion des invariants**

-   ✅ Vérification des limites
-   ✅ Vérification de verrouillage
-   ✅ Validation dans les Value Objects

### 6. **Tests unitaires**

-   ✅ Tests présents
-   ✅ Tests d'invariants
-   ✅ Tests isolés

---

## ⚠️ Points d'amélioration

### 1. **UserStatus - Flags binaires** ✅ **CORRIGÉ**

**Impact** : Les opérations bitwise fonctionnent maintenant correctement.

**Solution appliquée** : Constantes corrigées pour utiliser des puissances de 2 (ACTIVE = 1, BLOCKED = 2).

---

### 2. **Encapsulation des Value Objects** ✅ **CORRIGÉ**

**Impact** : Encapsulation DDD respectée.

**Solution appliquée** : Propriétés privées avec getters `get*()` dans tous les Value Objects.

---

### 3. **setId() problématique** ✅ **CORRIGÉ**

**Impact** : Immutabilité de l'identité respectée au niveau du langage.

**Solution appliquée** : Propriété `id` rendue `readonly`, méthode `setId()` supprimée, utilisation de la réflexion uniquement dans le repository pour la persistance.

---

### 4. **Gestion de updatedAt** ✅ **IMPLÉMENTÉ**

**Impact** : Contrôle total au niveau du domaine.

**Solution appliquée** : Gestion explicite de `updatedAt` via paramètre `DateTimeImmutable $now` dans chaque méthode métier, garantissant l'indépendance vis-à-vis de l'infrastructure.

---

### 5. **Validation manquante** ✅ **CORRIGÉ**

**Impact** : Validation complète via Value Objects.

**Solution appliquée** : Création de Value Objects `Username`, `Firstname`, `Lastname` avec validation dans les constructeurs.

---

### 6. **Bounded context Shop vide** 🟢 **MINEUR**

**Impact** : Structure confuse pour `Shop/` uniquement.

**Recommandation** : Nettoyer le dossier `Shop/` vide (SharedKernel contient maintenant les Domain Events).

---

### 7. **Absence de Domain Events** ✅ **CORRIGÉ**

**Impact** : Domain Events implémentés, permettant le découplage des bounded contexts.

**Solution appliquée** : Implémentation complète avec 8 Domain Events pour les actions importantes.

---

## 📊 Comparaison avec les principes DDD

| Principe DDD            | Respecté | Note   |
| ----------------------- | -------- | ------ |
| **Value Objects**       | ✅ Oui   | 10/10  |
| **Entities**            | ✅ Oui   | 10/10  |
| **Aggregates**          | ✅ Oui   | 9/10   |
| **Domain Exceptions**   | ✅ Oui   | 10/10  |
| **Bounded Contexts**    | ✅ Oui   | 9.5/10 |
| **Ubiquitous Language** | ✅ Oui   | 10/10  |
| **Encapsulation**       | ✅ Oui   | 9/10   |
| **Invariants**          | ✅ Oui   | 10/10  |
| **Immutability**        | ✅ Oui   | 9/10   |
| **Domain Logic**        | ✅ Oui   | 10/10  |
| **Tests Unitaires**     | ✅ Oui   | 8/10   |

---

## ✅ Conclusion

**Note finale : 9.5/10**

Le domaine respecte **excellemment** les principes DDD. Tous les problèmes critiques et moyens ont été corrigés :

**Points forts** :

-   ✅ Excellente logique métier encapsulée
-   ✅ Value Objects bien structurés avec encapsulation complète (propriétés privées + getters `get*()`)
-   ✅ Validation complète via Value Objects (`Username`, `Firstname`, `Lastname`, `EmailAddress`)
-   ✅ Isolation parfaite du domaine (pas de dépendances infrastructure)
-   ✅ Langage ubiquitaire excellent
-   ✅ Gestion exemplaire des invariants
-   ✅ Tests unitaires présents
-   ✅ Encapsulation respectée dans tous les Value Objects
-   ✅ Flags binaires corrigés (puissances de 2)
-   ✅ Immutabilité de l'identité renforcée (propriété `id` en `readonly`)
-   ✅ Gestion explicite de `updatedAt` au niveau du domaine (indépendance de l'infrastructure)
-   ✅ Convention get*()/set*() respectée
-   ✅ Validation métier complète dans tous les Value Objects
-   ✅ Exceptions problématiques supprimées (ActivationTokenException, ResetPasswordTokenException)
-   ✅ Toutes les exceptions ont maintenant des messages explicites
-   ✅ Organisation des exceptions par catégories (RateLimit/, Security/, Uniqueness/)
-   ✅ Nouvelles exceptions d'unicité (`EmailAlreadyUsedException`, `UsernameAlreadyUsedException`)
-   ✅ Domain Events implémentés (8 événements User + 2 événements Shop pour les actions importantes)
-   ✅ Factory method `createByAdmin()` pour la création par administrateur
-   ✅ Méthode `updateByAdmin()` pour regrouper les mises à jour administratives
-   ✅ Méthode `delete()` avec événement
-   ✅ API complète avec méthodes métier pour toutes les opérations (`changePassword`, `updateAvatar`, `updateUsername`, etc.)
-   ✅ Gestion explicite des timestamps au niveau du domaine avec paramètre `DateTimeImmutable $now` (User et Shop)
-   ✅ Contexte Shop complété avec tous les agrégats (`Product`, `Category`, `Order`, `OrderLine`, `Carrier`, `Address`)

**Points à améliorer** :

-   ✅ **RÉSOLU** : Bounded context Shop complété avec tous les agrégats nécessaires

**Comparaison avec les meilleures pratiques** :

| Aspect                  | État         |
| ----------------------- | ------------ |
| **Logique métier**      | ✅ Excellent |
| **Value Objects**       | ✅ Excellent |
| **Encapsulation**       | ✅ Excellent |
| **Isolation**           | ✅ Parfait   |
| **Langage ubiquitaire** | ✅ Parfait   |
| **Invariants**          | ✅ Parfait   |
| **Tests**               | ✅ Bon       |
| **Domain Events**       | ✅ Excellent |

Le domaine est **excellemment structuré** et suit les principes DDD. L'encapsulation des Value Objects, les flags binaires, l'immutabilité de l'identité (propriété `readonly`), la validation manquante et l'absence de Domain Events ont été corrigés avec succès. La création des Value Objects `Username`, `Firstname` et `Lastname` garantit une validation complète au niveau du domaine, et l'implémentation des Domain Events permet le découplage et la notification des changements importants. Le contexte Shop est maintenant complet avec tous les agrégats nécessaires, et les exceptions sont organisées par catégories pour une meilleure maintenabilité.

**État actuel (dernière mise à jour)** :

-   ✅ **Gestion des timestamps** : Approche explicite avec paramètre `DateTimeImmutable $now` dans toutes les méthodes métier qui modifient l'état, garantissant l'indépendance totale du domaine vis-à-vis de l'infrastructure
-   ✅ **API métier complète** : 20+ méthodes métier couvrant tous les cas d'usage (activation, réinitialisation de mot de passe, mises à jour, suppression)
-   ✅ **Immutabilité de createdAt** : Pas de setter `setCreatedAt()`, garantissant que cette date est immuable après la création
-   ✅ **17 propriétés** : Taille d'agrégat raisonnable et justifiée pour un agrégat User complet
-   ✅ **8 Domain Events User** : Tous les événements importants sont couverts et documentés
-   ✅ **2 Domain Events Shop** : `OrderPlacedEvent` et `OrderPaidEvent` pour gérer le cycle de vie des commandes
-   ✅ **5 exceptions organisées** : RateLimit (2), Security (1), Uniqueness (2) pour une meilleure organisation

La note est maintenant **9.5/10**.
