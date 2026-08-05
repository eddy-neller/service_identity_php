<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Domain\User\Event\Lifecycle\ActivationEmailRequestedEvent;
use App\Domain\User\Event\Lifecycle\UserActivatedEvent;
use App\Domain\User\Event\Lifecycle\UserRegisteredEvent;
use App\Domain\User\Event\Management\UserCreatedByAdminEvent;
use App\Domain\User\Event\Management\UserDeletedByAdminEvent;
use App\Domain\User\Event\Management\UserUpdatedByAdminEvent;
use App\Domain\User\Event\Profile\UserAvatarUpdatedEvent;
use App\Domain\User\Event\Security\PasswordResetCompletedEvent;
use App\Domain\User\Event\Security\PasswordResetRequestedEvent;
use App\Domain\User\Event\Security\UserPasswordUpdatedEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptRegisteredEvent;
use App\Domain\User\Event\Security\UserWrongPasswordAttemptsResetEvent;
use App\Infrastructure\Service\Cache\QueryCacheInterface;
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
