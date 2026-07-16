<?php

declare(strict_types=1);

namespace App\Domain\User\Model;

use App\Domain\SharedKernel\Event\DomainEventTrait;
use App\Domain\User\Event\ActivationEmailRequestedEvent;
use App\Domain\User\Event\PasswordResetCompletedEvent;
use App\Domain\User\Event\PasswordResetRequestedEvent;
use App\Domain\User\Event\UserActivatedEvent;
use App\Domain\User\Event\UserAvatarUpdatedEvent;
use App\Domain\User\Event\UserCreatedByAdminEvent;
use App\Domain\User\Event\UserDeletedByAdminEvent;
use App\Domain\User\Event\UserPasswordUpdatedEvent;
use App\Domain\User\Event\UserRegisteredEvent;
use App\Domain\User\Event\UserUpdatedByAdminEvent;
use App\Domain\User\Event\UserWrongPasswordAttemptRegisteredEvent;
use App\Domain\User\Event\UserWrongPasswordAttemptsResetEvent;
use App\Domain\User\Exception\RateLimit\ActivationLimitReachedException;
use App\Domain\User\Exception\RateLimit\ResetPasswordLimitReachedException;
use App\Domain\User\Exception\Security\UserLockedException;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\ValueObject\Avatar;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Firstname;
use App\Domain\User\ValueObject\Lastname;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\ActiveEmail;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\Security;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
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
        private Avatar $avatar,
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
            security: new Security(),
            activeEmail: new ActiveEmail(),
            resetPassword: new ResetPassword(),
            preferences: $preferences,
            avatar: new Avatar(),
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
            security: new Security(),
            activeEmail: new ActiveEmail(),
            resetPassword: new ResetPassword(),
            preferences: $preferences ?? new Preferences(),
            avatar: new Avatar(),
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
        Avatar $avatar,
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
            avatar: $avatar,
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

        $this->activeEmail = new ActiveEmail(
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
        $this->activeEmail = new ActiveEmail();
    }

    public function requestPasswordReset(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->assertNotLocked();
        $this->refreshResetPasswordIfExpired($now);

        if ($this->getResetPassword()->getMailSent() >= self::MAX_TOKEN_REQUESTS) {
            throw new ResetPasswordLimitReachedException();
        }

        $this->resetPassword = new ResetPassword(
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
        $this->resetPassword = new ResetPassword();
        $this->touch($now);

        $this->recordEvent(new PasswordResetCompletedEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function registerWrongPasswordAttempt(int $maxAttempts, DateTimeImmutable $now): void
    {
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
    }

    public function updateAvatar(Avatar $avatar, DateTimeImmutable $now): void
    {
        $this->avatar = $avatar;
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
        }
    }

    public function deleteByAdmin(DateTimeImmutable $now): void
    {
        $this->recordEvent(new UserDeletedByAdminEvent(
            userId: $this->id,
            occurredOn: $now,
        ));
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

    public function getUsername(): Username
    {
        return $this->username;
    }

    public function getFirstname(): ?Firstname
    {
        return $this->firstname;
    }

    public function getLastname(): ?Lastname
    {
        return $this->lastname;
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

    public function getAvatar(): Avatar
    {
        return $this->avatar;
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
            $this->activeEmail = new ActiveEmail();
        }
    }

    private function refreshResetPasswordIfExpired(DateTimeImmutable $now): void
    {
        $resetPassword = $this->getResetPassword();
        $ttl = $resetPassword->getTokenTtl();

        if (null !== $ttl && $ttl <= $now->getTimestamp()) {
            $this->resetPassword = new ResetPassword();
        }
    }

    private function assertNotLocked(): void
    {
        if ($this->getStatus()->isBlocked()) {
            throw new UserLockedException();
        }
    }
}
