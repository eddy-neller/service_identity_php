<?php

declare(strict_types=1);

namespace App\Domain\User\Event\Lifecycle;

use App\Domain\SharedKernel\Event\DomainEventInterface;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use DateTimeImmutable;

final readonly class ActivationEmailRequestedEvent implements DomainEventInterface
{
    public function __construct(
        private UserId $userId,
        private EmailAddress $email,
        private DateTimeImmutable $occurredOn,
    ) {
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.activation_email.requested';
    }
}
