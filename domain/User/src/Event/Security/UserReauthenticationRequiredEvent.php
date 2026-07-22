<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Security;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use DateTimeImmutable;

final readonly class UserReauthenticationRequiredEvent implements DomainEventInterface
{
    public function __construct(
        private UserId $userId,
        private ReauthenticationReason $reason,
        private DateTimeImmutable $occurredOn,
    ) {
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getReason(): ReauthenticationReason
    {
        return $this->reason;
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
