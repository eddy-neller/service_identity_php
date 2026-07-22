<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Profile;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use DateTimeImmutable;

final readonly class UserAvatarUpdatedEvent implements DomainEventInterface
{
    public function __construct(
        private UserId $userId,
        private DateTimeImmutable $occurredOn,
    ) {
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.avatar_updated';
    }
}
