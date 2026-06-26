<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Application\Shared\Port\QueryCacheInterface;
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
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'user.created_by_admin', method: 'onUserEvent')]
#[AsEventListener(event: 'user.registered', method: 'onUserEvent')]
#[AsEventListener(event: 'user.updated_by_admin', method: 'onUserEvent')]
#[AsEventListener(event: 'user.deleted', method: 'onUserEvent')]
#[AsEventListener(event: 'user.activated', method: 'onUserEvent')]
#[AsEventListener(event: 'user.password_reset.requested', method: 'onUserEvent')]
#[AsEventListener(event: 'user.password_reset.completed', method: 'onUserEvent')]
#[AsEventListener(event: 'user.activation_email.requested', method: 'onUserEvent')]
#[AsEventListener(event: 'user.avatar_updated', method: 'onUserEvent')]
#[AsEventListener(event: 'user.password.updated', method: 'onUserEvent')]
#[AsEventListener(event: 'user.wrong_password_attempts.reset', method: 'onUserEvent')]
#[AsEventListener(event: 'user.wrong_password_attempt.registered', method: 'onUserEvent')]
final readonly class UserCacheInvalidationListener
{
    public function __construct(
        private QueryCacheInterface $cache,
    ) {
    }

    public function onUserEvent(
        UserCreatedByAdminEvent
        |UserRegisteredEvent
        |UserUpdatedByAdminEvent
        |UserDeletedByAdminEvent
        |UserActivatedEvent
        |PasswordResetRequestedEvent
        |PasswordResetCompletedEvent
        |ActivationEmailRequestedEvent
        |UserAvatarUpdatedEvent
        |UserPasswordUpdatedEvent
        |UserWrongPasswordAttemptRegisteredEvent
        |UserWrongPasswordAttemptsResetEvent $event,
    ): void {
        $userId = $event->getUserId()->toString();

        $this->cache->invalidateTags([
            'users-collection',
            'user-' . $userId,
        ]);
    }
}
