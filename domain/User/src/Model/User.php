<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

use App\Domain\SharedKernel\Event\DomainEventTrait;
use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Domain\User\Event\Lifecycle\UserActivatedEvent;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\Event\Management\UserUpdatedByAdminEvent;
use App\Domain\User\Event\Profile\UserAvatarUpdatedEvent;
use App\Domain\User\Event\Security\PasswordResetCompletedEvent;
use App\Domain\User\Event\Security\PasswordResetRequestedEvent;
use App\Domain\User\Event\Security\ReauthenticationReason;
use App\Domain\User\Event\Security\UserPasswordUpdatedEvent;
use App\Domain\User\Event\Security\UserReauthenticationRequiredEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptRegisteredEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptsResetEvent;
use App\Domain\User\Exception\RateLimit\ActivationLimitReachedException;
use App\Domain\User\Exception\RateLimit\ResetPasswordLimitReachedException;
use App\Domain\User\Exception\Security\UserLockedException;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\ActiveEmail;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Profile\Firstname;
use App\Domain\User\ValueObject\Profile\Lastname;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Domain\User\ValueObject\Security\Security;
use DateTimeImmutable;

final class User
{
    use DomainEventTrait;

    private const int MAX_TOKEN_REQUESTS = 3;

    private function __construct(
        private UserId $id,
        private Username $username,
        private ?Firstname $firstname,
        private ?Lastname $lastname,
        private EmailAddress $email,
        private HashedPassword $password,
        private RoleSet $roles,
        private UserStatus $status,
        private Security $security,
        private ActiveEmail $activeEmail,
        private ResetPassword $resetPassword,
        private Preferences $preferences,
        private ?string $avatarName,
        private DateTimeImmutable $lastVisit,
        private int $loginCount,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

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
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            password: $password,
            roles: RoleSet::fromArray(['ROLE_USER']),
            status: UserStatus::inactive(),
            security: Security::create(),
            activeEmail: ActiveEmail::create(),
            resetPassword: ResetPassword::create(),
            preferences: $preferences,
            avatarName: null,
            lastVisit: $now,
            loginCount: 0,
            createdAt: $now,
            updatedAt: $now,
        );

        $user->recordEvent(new UserRegisteredEvent(
            userId: $id,
            email: $email,
            occurredOn: $now,
        ));

        return $user;
    }

    public static function createByAdmin(
        UserId $id,
        Username $username,
        EmailAddress $email,
        HashedPassword $password,
        RoleSet $roles,
        UserStatus $status,
        DateTimeImmutable $now,
        ?Firstname $firstname = null,
        ?Lastname $lastname = null,
        ?Preferences $preferences = null,
    ): self {
        $user = new self(
            id: $id,
            username: $username,
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            password: $password,
            roles: $roles,
            status: $status,
            security: Security::create(),
            activeEmail: ActiveEmail::create(),
            resetPassword: ResetPassword::create(),
            preferences: $preferences ?? Preferences::create(),
            avatarName: null,
            lastVisit: $now,
            loginCount: 0,
            createdAt: $now,
            updatedAt: $now,
        );

        $user->recordEvent(new UserCreatedByAdminEvent(
            userId: $id,
            email: $email,
            occurredOn: $now,
        ));

        return $user;
    }

    public static function reconstitute(
        UserId $id,
        Username $username,
        EmailAddress $email,
        HashedPassword $password,
        RoleSet $roles,
        UserStatus $status,
        Security $security,
        ActiveEmail $activeEmail,
        ResetPassword $resetPassword,
        Preferences $preferences,
        ?string $avatarName,
        DateTimeImmutable $lastVisit,
        int $loginCount,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?Firstname $firstname = null,
        ?Lastname $lastname = null,
    ): self {
        return new self(
            id: $id,
            username: $username,
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            password: $password,
            roles: $roles,
            status: $status,
            security: $security,
            activeEmail: $activeEmail,
            resetPassword: $resetPassword,
            preferences: $preferences,
            avatarName: $avatarName,
            lastVisit: $lastVisit,
            loginCount: $loginCount,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function requestActivation(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->assertNotLocked();
        $this->refreshActivationIfExpired($now);

        if ($this->getActiveEmail()->getMailSent() >= self::MAX_TOKEN_REQUESTS) {
            throw new ActivationLimitReachedException();
        }

        $this->activeEmail = ActiveEmail::create(
            mailSent: $this->getActiveEmail()->getMailSent() + 1,
            token: $token,
            tokenTtl: $expiresAt->getTimestamp(),
            lastAttempt: $now,
        );

        $this->recordEvent(new ActivationEmailRequestedEvent(
            userId: $this->id,
            email: $this->email,
            occurredOn: $now,
        ));
    }

    public function activate(string $token, DateTimeImmutable $now): void
    {
        $this->assertNotLocked();
        $this->assertActivationTokenValid($token, $now);
        $this->status = UserStatus::active();
        $this->clearActivation();
        $this->touch($now);

        $this->recordEvent(new UserActivatedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function clearActivation(): void
    {
        $this->activeEmail = ActiveEmail::create();
    }

    public function requestPasswordReset(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->assertNotLocked();
        $this->refreshResetPasswordIfExpired($now);

        if ($this->getResetPassword()->getMailSent() >= self::MAX_TOKEN_REQUESTS) {
            throw new ResetPasswordLimitReachedException();
        }

        $this->resetPassword = ResetPassword::create(
            mailSent: $this->getResetPassword()->getMailSent() + 1,
            token: $token,
            tokenTtl: $expiresAt->getTimestamp(),
        );

        $this->recordEvent(new PasswordResetRequestedEvent(
            userId: $this->id,
            email: $this->email,
            occurredOn: $now,
        ));
    }

    public function completePasswordReset(string $token, HashedPassword $password, DateTimeImmutable $now): void
    {
        $this->assertResetPasswordTokenValid($token, $now);
        $this->password = $password;
        $this->resetPassword = ResetPassword::create();
        $this->touch($now);

        $this->recordEvent(new PasswordResetCompletedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));

        $this->requireReauthentication(ReauthenticationReason::PASSWORD_RESET, $now);
    }

    public function registerWrongPasswordAttempt(int $maxAttempts, DateTimeImmutable $now): void
    {
        $wasLocked = $this->isLocked();
        $attempts = $this->security->getTotalWrongPassword() + 1;
        $this->security = $this->security->withTotalWrongPassword($attempts);

        if ($attempts >= $maxAttempts) {
            $this->status = UserStatus::blocked();
        }

        $this->touch($now);

        $this->recordEvent(new UserWrongPasswordAttemptRegisteredEvent(
            userId: $this->id,
            occurredOn: $now,
        ));

        if (!$wasLocked && $this->isLocked()) {
            $this->requireReauthentication(ReauthenticationReason::ACCOUNT_LOCKED, $now);
        }
    }

    public function resetWrongPasswordAttempts(DateTimeImmutable $now): void
    {
        if (0 === $this->security->getTotalWrongPassword()) {
            return;
        }

        $this->security = $this->security->withTotalWrongPassword(0);
        if ($this->getStatus()->isBlocked()) {
            $this->status = UserStatus::active();
        }

        $this->touch($now);

        $this->recordEvent(new UserWrongPasswordAttemptsResetEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function changePassword(HashedPassword $password, DateTimeImmutable $now): void
    {
        $this->password = $password;
        $this->touch($now);

        $this->recordEvent(new UserPasswordUpdatedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));

        $this->requireReauthentication(ReauthenticationReason::PASSWORD_CHANGED, $now);
    }

    public function recordSuccessfulLogin(DateTimeImmutable $now): void
    {
        ++$this->loginCount;
        $this->lastVisit = $now;
        $this->touch($now);
    }

    public function updateAvatar(string $avatarName, DateTimeImmutable $now): void
    {
        $this->avatarName = $avatarName;
        $this->touch($now);

        $this->recordEvent(new UserAvatarUpdatedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function updateByAdmin(
        DateTimeImmutable $now,
        ?Username $username = null,
        ?EmailAddress $email = null,
        ?Firstname $firstname = null,
        ?Lastname $lastname = null,
        ?RoleSet $roles = null,
        ?UserStatus $status = null,
        ?HashedPassword $password = null,
    ): void {
        $hasChanges = false;
        $accessDisabled = null !== $status && $this->status->isActive() && !$status->isActive();

        if (null !== $username) {
            $this->username = $username;
            $hasChanges = true;
        }

        if (null !== $email) {
            $this->email = $email;
            $hasChanges = true;
        }

        if (null !== $firstname) {
            $this->firstname = $firstname;
            $hasChanges = true;
        }

        if (null !== $lastname) {
            $this->lastname = $lastname;
            $hasChanges = true;
        }

        if (null !== $roles) {
            $this->roles = $roles;
            $hasChanges = true;
        }

        if (null !== $status) {
            $this->status = $status;
            $hasChanges = true;
        }

        if (null !== $password) {
            $this->password = $password;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->touch($now);

            $this->recordEvent(new UserUpdatedByAdminEvent(
                userId: $this->id,
                occurredOn: $now,
            ));

            if (null !== $password) {
                $this->requireReauthentication(ReauthenticationReason::PASSWORD_CHANGED, $now);
            } elseif (null !== $roles) {
                $this->requireReauthentication(ReauthenticationReason::ROLES_CHANGED, $now);
            } elseif ($accessDisabled) {
                $this->requireReauthentication(ReauthenticationReason::ACCESS_DISABLED, $now);
            }
        }
    }

    public function deleteByAdmin(DateTimeImmutable $now): void
    {
        $this->recordEvent(new UserDeletedByAdminEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
        $this->requireReauthentication(ReauthenticationReason::ACCOUNT_DELETED, $now);
    }

    public function isActive(): bool
    {
        return $this->getStatus()->isActive();
    }

    public function isLocked(): bool
    {
        return $this->getStatus()->isBlocked();
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getFirstname(): ?Firstname
    {
        return $this->firstname;
    }

    public function getLastname(): ?Lastname
    {
        return $this->lastname;
    }

    public function getUsername(): Username
    {
        return $this->username;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function getPassword(): HashedPassword
    {
        return $this->password;
    }

    public function getRoles(): RoleSet
    {
        return $this->roles;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function getSecurity(): Security
    {
        return $this->security;
    }

    public function getActiveEmail(): ActiveEmail
    {
        return $this->activeEmail;
    }

    public function getResetPassword(): ResetPassword
    {
        return $this->resetPassword;
    }

    public function getPreferences(): Preferences
    {
        return $this->preferences;
    }

    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }

    public function getLastVisit(): DateTimeImmutable
    {
        return $this->lastVisit;
    }

    public function getLoginCount(): int
    {
        return $this->loginCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    private function requireReauthentication(ReauthenticationReason $reason, DateTimeImmutable $now): void
    {
        $this->recordEvent(new UserReauthenticationRequiredEvent(
            userId: $this->id,
            reason: $reason,
            occurredOn: $now,
        ));
    }

    private function assertActivationTokenValid(string $token, DateTimeImmutable $now): void
    {
        $activeEmail = $this->getActiveEmail();
        $ttl = $activeEmail->getTokenTtl() ?? 0;

        if ($ttl <= 0 || $ttl <= $now->getTimestamp()) {
            throw new UserDomainException("Token d'activation expiré.");
        }

        if ($activeEmail->getToken() !== $token) {
            throw new UserDomainException("Token d'activation invalide.");
        }
    }

    private function assertResetPasswordTokenValid(string $token, DateTimeImmutable $now): void
    {
        $resetPassword = $this->getResetPassword();
        $ttl = $resetPassword->getTokenTtl() ?? 0;

        if ($ttl <= 0 || $ttl <= $now->getTimestamp()) {
            throw new UserDomainException('Token de réinitialisation expiré.');
        }

        if ($resetPassword->getToken() !== $token) {
            throw new UserDomainException('Token de réinitialisation invalide.');
        }
    }

    private function refreshActivationIfExpired(DateTimeImmutable $now): void
    {
        $activeEmail = $this->getActiveEmail();
        $ttl = $activeEmail->getTokenTtl();

        if (null !== $ttl && $ttl <= $now->getTimestamp()) {
            $this->activeEmail = ActiveEmail::create();
        }
    }

    private function refreshResetPasswordIfExpired(DateTimeImmutable $now): void
    {
        $resetPassword = $this->getResetPassword();
        $ttl = $resetPassword->getTokenTtl();

        if (null !== $ttl && $ttl <= $now->getTimestamp()) {
            $this->resetPassword = ResetPassword::create();
        }
    }

    private function assertNotLocked(): void
    {
        if ($this->getStatus()->isBlocked()) {
            throw new UserLockedException();
        }
    }
}
