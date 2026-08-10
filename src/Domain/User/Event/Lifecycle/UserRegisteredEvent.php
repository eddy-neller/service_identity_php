<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Lifecycle;

use App\Domain\SharedKernel\Event\DomainEventIdentityTrait;
use App\Domain\User\Event\UserDomainEventInterface;
use App\Domain\User\ValueObject\Identity\UserId;
use DateTimeImmutable;

final readonly class UserRegisteredEvent implements UserDomainEventInterface
{
    use DomainEventIdentityTrait;

    public function __construct(
        private UserId $userId,
        private DateTimeImmutable $occurredOn,
    ) {
        $this->eventId = self::generateEventId();
    }

    public function getUserId(): UserId
    {
        return $this->userId;
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
        return 'user.registered';
    }
}
