<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\ActiveEmail;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Domain\User\ValueObject\Security\Security;
use DateTimeImmutable;
use Deprecated;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UserUsernameUniq', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'UserEmailUniq', columns: ['email'])]
#[ORM\Index(name: 'UserCreatedAtIdx', columns: ['created_at'])]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected UuidInterface $id;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $firstname = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $lastname = null;

    #[ORM\Column(type: Types::STRING)]
    private string $username;

    #[ORM\Column(type: Types::STRING)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::SMALLINT)]
    private int $status = UserStatus::INACTIVE;

    #[ORM\Column(type: Types::JSON)]
    private array $security;

    #[ORM\Column(type: Types::JSON)]
    private array $activeEmail;

    #[ORM\Column(type: Types::JSON)]
    private array $resetPassword;

    #[ORM\Column(type: Types::JSON)]
    private array $preferences = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $avatarName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastVisit;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nbLogin = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    protected DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->security = Security::create()->toArray();
        $this->activeEmail = ActiveEmail::create()->toArray();
        $this->resetPassword = ResetPassword::create()->toArray();
        $this->lastVisit = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getFullname(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
        return null;
    }

    #[Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantee every user at least has ROLE_USER
        if (empty($roles)) {
            $roles[] = RoleSet::ROLE_USER;
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSecurity(): Security
    {
        return Security::fromArray($this->security);
    }

    public function setSecurity(Security $security): self
    {
        $this->security = $security->toArray();

        return $this;
    }

    public function getActiveEmail(): ActiveEmail
    {
        return ActiveEmail::fromArray($this->activeEmail);
    }

    public function setActiveEmail(ActiveEmail $activeEmail): self
    {
        $this->activeEmail = $activeEmail->toArray();

        return $this;
    }

    public function getResetPassword(): ResetPassword
    {
        return ResetPassword::fromArray($this->resetPassword);
    }

    public function setResetPassword(ResetPassword $resetPassword): self
    {
        $this->resetPassword = $resetPassword->toArray();

        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;

        return $this;
    }

    public function setAvatarName(?string $avatarName): self
    {
        $this->avatarName = $avatarName;

        return $this;
    }

    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }

    public function getLastVisit(): DateTimeImmutable
    {
        return $this->lastVisit;
    }

    public function setLastVisit(DateTimeImmutable $lastVisit): self
    {
        $this->lastVisit = $lastVisit;

        return $this;
    }

    public function getNbLogin(): int
    {
        return $this->nbLogin;
    }

    public function setNbLogin(mixed $nbLogin): self
    {
        $this->nbLogin = $nbLogin;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPreferredLang(): ?string
    {
        return $this->getPreferences()['lang'] ?? null;
    }
}
