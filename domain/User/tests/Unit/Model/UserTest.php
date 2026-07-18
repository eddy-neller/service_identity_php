<?php

declare(strict_types=1);

namespace App\Domain\User\Tests\Unit\Model;

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
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\ActiveEmail;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\ResetPassword;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class UserTest extends TestCase
{
    public function testRegisterCreatesInactiveUserWithDefaultRole(): void
    {
        $user = $this->createUser();

        $this->assertFalse($user->isActive());
        $this->assertSame(['ROLE_USER'], $user->getRoles()->all());
        $this->assertSame(UserStatus::INACTIVE, $user->getStatus()->toInt());
        $this->assertCount(1, $user->getDomainEvents());
    }

    public function testRegisterRecordsUserRegisteredEvent(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = User::register(
            id: $userId,
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRegisteredEvent::class, $events[0]);
    }

    public function testCreateByAdminCreatesUserWithCustomRolesAndStatus(): void
    {
        $roles = ['ROLE_ADMIN', 'ROLE_USER'];
        $user = User::createByAdmin(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('admin'),
            email: EmailAddress::fromString('admin@example.com'),
            password: HashedPassword::fromString('hash'),
            roles: RoleSet::fromArray($roles),
            status: UserStatus::fromInt(UserStatus::ACTIVE),
            now: new DateTimeImmutable(),
        );

        $this->assertSame($roles, $user->getRoles()->all());
        $this->assertTrue($user->isActive());
        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserCreatedByAdminEvent::class, $events[0]);
    }

    public function testRequestActivationIncrementsMailSentAndStoresToken(): void
    {
        $user = $this->createUser();
        $token = 'activation-token';
        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable('+1 day');

        $user->clearDomainEvents();
        $user->requestActivation($token, $expiresAt, $now);

        $this->assertSame(1, $user->getActiveEmail()->getMailSent());
        $this->assertSame($token, $user->getActiveEmail()->getToken());
        $this->assertSame($expiresAt->getTimestamp(), $user->getActiveEmail()->getTokenTtl());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ActivationEmailRequestedEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($user->getEmail(), $event->getEmail());
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.activation_email.requested', $event->eventName());
    }

    public function testRequestActivationThrowsWhenLimitReached(): void
    {
        $user = $this->createUser();
        $this->setActiveEmail($user, new ActiveEmail(mailSent: 3));

        $this->expectException(ActivationLimitReachedException::class);

        $user->requestActivation('token', new DateTimeImmutable('+1 day'), new DateTimeImmutable());
    }

    public function testRequestActivationResetsCounterWhenPreviousTokenExpired(): void
    {
        $user = $this->createUser();
        $date = new DateTimeImmutable('-1 hour');

        $expiredTtl = $date->getTimestamp();
        $this->setActiveEmail($user, new ActiveEmail(mailSent: 3, token: 'old', tokenTtl: $expiredTtl));

        $now = new DateTimeImmutable();
        $user->requestActivation('token', new DateTimeImmutable('+1 day'), $now);

        $this->assertSame(1, $user->getActiveEmail()->getMailSent());
        $this->assertSame('token', $user->getActiveEmail()->getToken());
    }

    public function testActivateSetsUserActiveAndClearsActivationToken(): void
    {
        $user = $this->createUser();
        $token = 'token';
        $user->requestActivation($token, new DateTimeImmutable('+1 day'), new DateTimeImmutable());
        $user->clearDomainEvents(); // Clear previous events

        $now = new DateTimeImmutable();
        $user->activate($token, $now);

        $this->assertTrue($user->isActive());
        $this->assertNull($user->getActiveEmail()->getToken());
        $this->assertSame(0, $user->getActiveEmail()->getMailSent());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserActivatedEvent::class, $events[0]);
    }

    public function testRequestPasswordResetStoresTokenAndIncrementsMailSent(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = EmailAddress::fromString('john@example.com');
        $user = User::register(
            id: $userId,
            username: Username::fromString('john'),
            email: $email,
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
        $user->clearActivation(); // already inactive but clears TTL checks
        $user->clearDomainEvents();

        $token = 'reset-token';
        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable('+15 minutes');

        $user->requestPasswordReset($token, $expiresAt, $now);

        $this->assertSame(1, $user->getResetPassword()->getMailSent());
        $this->assertSame($token, $user->getResetPassword()->getToken());
        $this->assertSame($expiresAt->getTimestamp(), $user->getResetPassword()->getTokenTtl());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PasswordResetRequestedEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($userId->equals($event->getUserId()));
        $this->assertSame($email, $event->getEmail());
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.password_reset.requested', $event->eventName());
    }

    public function testRequestPasswordResetThrowsWhenUserIsLocked(): void
    {
        $user = $this->createUser();
        $this->setStatus($user, UserStatus::fromInt(UserStatus::BLOCKED));

        $this->expectException(UserLockedException::class);

        $user->requestPasswordReset('token', new DateTimeImmutable('+15 minutes'), new DateTimeImmutable());
    }

    public function testRequestPasswordResetThrowsWhenLimitReached(): void
    {
        $user = $this->createActiveUser();
        $this->setResetPassword($user, new ResetPassword(mailSent: 3));

        $this->expectException(ResetPasswordLimitReachedException::class);

        $user->requestPasswordReset('token', new DateTimeImmutable('+15 minutes'), new DateTimeImmutable());
    }

    public function testRequestPasswordResetResetsCounterWhenPreviousTokenExpired(): void
    {
        $user = $this->createActiveUser();
        $date = new DateTimeImmutable('-1 hour');

        $expiredTtl = $date->getTimestamp();
        $this->setResetPassword($user, new ResetPassword(mailSent: 3, token: 'old', tokenTtl: $expiredTtl));

        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable('+15 minutes');

        $user->requestPasswordReset('token', $expiresAt, $now);

        $this->assertSame(1, $user->getResetPassword()->getMailSent());
        $this->assertSame('token', $user->getResetPassword()->getToken());
        $this->assertSame($expiresAt->getTimestamp(), $user->getResetPassword()->getTokenTtl());
    }

    public function testCompletePasswordResetChangesPasswordAndClearsToken(): void
    {
        $user = $this->createActiveUser();
        $token = 'reset-token';
        $expiresAt = new DateTimeImmutable('+15 minutes');
        $user->requestPasswordReset($token, $expiresAt, new DateTimeImmutable());
        $user->clearDomainEvents();

        $newPassword = HashedPassword::fromString('new-hashed-password');
        $now = new DateTimeImmutable();

        $user->completePasswordReset($token, $newPassword, $now);

        $this->assertSame($newPassword, $user->getPassword());
        $this->assertNull($user->getResetPassword()->getToken());
        $this->assertSame(0, $user->getResetPassword()->getMailSent());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(PasswordResetCompletedEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.password_reset.completed', $event->eventName());
    }

    public function testChangePasswordUpdatesPasswordOnly(): void
    {
        $user = $this->createActiveUser();
        $oldPassword = $user->getPassword();
        $newPassword = HashedPassword::fromString('new-password');
        $now = new DateTimeImmutable();

        $user->changePassword($newPassword, $now);

        $this->assertSame($newPassword, $user->getPassword());
        $this->assertNotSame($oldPassword, $user->getPassword());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserPasswordUpdatedEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.password.updated', $event->eventName());
    }

    public function testUpdateAvatarRecordsEvent(): void
    {
        $user = $this->createActiveUser();
        $avatar = 'avatar.png';
        $now = new DateTimeImmutable();

        $user->updateAvatar($avatar, $now);

        $this->assertSame($avatar, $user->getAvatarName());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserAvatarUpdatedEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.avatar_updated', $event->eventName());
    }

    public function testDeleteRecordsEvent(): void
    {
        $user = $this->createActiveUser();
        $now = new DateTimeImmutable();

        $user->deleteByAdmin($now);

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserDeletedByAdminEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.deleted', $event->eventName());
    }

    public function testUpdateByAdminUpdatesOnlyProvidedFields(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user = User::register(
            id: $userId,
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
        $user->clearActivation();
        $user->clearDomainEvents();

        $originalEmail = $user->getEmail();
        $newUsername = Username::fromString('updated-username');
        $now = new DateTimeImmutable();

        $user->updateByAdmin(
            now: $now,
            username: $newUsername,
        );

        $this->assertSame($newUsername, $user->getUsername());
        $this->assertSame($originalEmail, $user->getEmail());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserUpdatedByAdminEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($userId->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.updated_by_admin', $event->eventName());
    }

    public function testUpdateByAdminDoesNotRecordEventWhenNoChanges(): void
    {
        $user = $this->createActiveUser();
        $now = new DateTimeImmutable();

        $user->updateByAdmin(now: $now);

        $events = $user->getDomainEvents();
        $this->assertCount(0, $events);
    }

    public function testIsLockedReturnsTrueWhenUserBlocked(): void
    {
        $user = $this->createUser();
        $this->setStatus($user, UserStatus::blocked());

        $this->assertTrue($user->isLocked());
    }

    public function testIsActiveReturnsFalseForInactiveUser(): void
    {
        $user = $this->createUser();

        $this->assertFalse($user->isActive());
    }

    public function testEqualsReturnsTrueForSameUser(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user1 = User::register(
            id: $userId,
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
        $user2 = User::register(
            id: $userId,
            username: Username::fromString('jane'),
            email: EmailAddress::fromString('jane@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'en']),
            now: new DateTimeImmutable(),
        );

        $this->assertTrue($user1->equals($user2));
    }

    public function testEqualsReturnsFalseForDifferentUsers(): void
    {
        $user1 = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
        $user2 = User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            username: Username::fromString('jane'),
            email: EmailAddress::fromString('jane@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'en']),
            now: new DateTimeImmutable(),
        );

        $this->assertFalse($user1->equals($user2));
    }

    private function createUser(): User
    {
        return User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('john'),
            email: EmailAddress::fromString('john@example.com'),
            password: HashedPassword::fromString('hash'),
            preferences: Preferences::fromArray(['lang' => 'fr']),
            now: new DateTimeImmutable(),
        );
    }

    private function createActiveUser(): User
    {
        $user = $this->createUser();
        $token = 'token';
        $user->requestActivation($token, new DateTimeImmutable('+1 day'), new DateTimeImmutable());
        $user->activate($token, new DateTimeImmutable());
        $user->clearDomainEvents(); // Clear events

        return $user;
    }

    public function testRegisterWrongPasswordAttemptBlocksUserAfterThreshold(): void
    {
        $user = $this->createActiveUser();
        $now = new DateTimeImmutable();

        $user->registerWrongPasswordAttempt(2, $now);
        $this->assertFalse($user->isLocked());

        $user->registerWrongPasswordAttempt(2, $now);
        $this->assertTrue($user->isLocked());

        $events = $user->getDomainEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(UserWrongPasswordAttemptRegisteredEvent::class, $events[1]);

        $event = $events[1];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.wrong_password_attempt.registered', $event->eventName());
    }

    public function testResetWrongPasswordAttemptsClearsCounter(): void
    {
        $user = $this->createActiveUser();
        $now = new DateTimeImmutable();

        $user->registerWrongPasswordAttempt(5, $now);
        $this->assertSame(1, $user->getSecurity()->getTotalWrongPassword());
        $user->clearDomainEvents();

        $user->resetWrongPasswordAttempts($now);
        $this->assertSame(0, $user->getSecurity()->getTotalWrongPassword());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserWrongPasswordAttemptsResetEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($now, $event->occurredOn());
        $this->assertSame('user.wrong_password_attempts.reset', $event->eventName());
    }

    public function testResetWrongPasswordAttemptsUnblocksUser(): void
    {
        $user = $this->createActiveUser();
        $now = new DateTimeImmutable();

        $user->registerWrongPasswordAttempt(1, $now);
        $this->assertTrue($user->isLocked());
        $user->clearDomainEvents();

        $resetNow = new DateTimeImmutable('+1 minute');
        $user->resetWrongPasswordAttempts($resetNow);

        $this->assertFalse($user->isLocked());
        $this->assertTrue($user->isActive());
        $this->assertSame(0, $user->getSecurity()->getTotalWrongPassword());
        $this->assertSame($resetNow, $user->getUpdatedAt());

        $events = $user->getDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserWrongPasswordAttemptsResetEvent::class, $events[0]);

        $event = $events[0];
        $this->assertTrue($user->getId()->equals($event->getUserId()));
        $this->assertSame($resetNow, $event->occurredOn());
        $this->assertSame('user.wrong_password_attempts.reset', $event->eventName());
    }

    private function setActiveEmail(User $user, ActiveEmail $activeEmail): void
    {
        $this->setProperty($user, 'activeEmail', $activeEmail);
    }

    private function setStatus(User $user, UserStatus $status): void
    {
        $this->setProperty($user, 'status', $status);
    }

    private function setResetPassword(User $user, ResetPassword $resetPassword): void
    {
        $this->setProperty($user, 'resetPassword', $resetPassword);
    }

    private function setProperty(User $user, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(User::class, $property);
        $reflection->setValue($user, $value);
    }
}
