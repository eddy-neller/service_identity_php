<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Security;

use App\Domain\SharedKernel\Event\DomainEventIdentityTrait;
use App\Domain\User\Event\UserDomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use DateTimeImmutable;

final readonly class UserReauthenticationRequiredEvent implements UserDomainEventInterface
{
    use DomainEventIdentityTrait;

    public function __construct(
        private UserId $userId,
        private ReauthenticationReason $reason,
        private DateTimeImmutable $occurredOn,
    ) {
        $this->eventId = self::generateEventId();
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getReason(): ReauthenticationReason
    {
        return $this->reason;
    }

    public function aggregateId(): string
    {
        return $this->userId->toString();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.reauthentication.required';
    }
}
