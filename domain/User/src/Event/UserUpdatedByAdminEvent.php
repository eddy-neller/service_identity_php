<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\ValueObject\UserId;
use DateTimeImmutable;

final readonly class UserUpdatedByAdminEvent implements DomainEventInterface
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
        return 'user.updated_by_admin';
    }
}
