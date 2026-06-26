<?php

declare(strict_types=1);

namespace App\Application\User\ReadModel;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Avatar;
use DateTimeImmutable;

final readonly class UserItem
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public ?string $firstname,
        public ?string $lastname,
        public string $username,
        public string $email,
        public array $roles,
        public int $status,
        public Avatar $avatar,
        public DateTimeImmutable $lastVisit,
        public int $loginCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->getId()->toString(),
            firstname: $user->getFirstname()?->toString(),
            lastname: $user->getLastname()?->toString(),
            username: $user->getUsername()->toString(),
            email: $user->getEmail()->toString(),
            roles: $user->getRoles()->all(),
            status: $user->getStatus()->toInt(),
            avatar: $user->getAvatar(),
            lastVisit: $user->getLastVisit(),
            loginCount: $user->getLoginCount(),
            createdAt: $user->getCreatedAt(),
            updatedAt: $user->getUpdatedAt(),
        );
    }
}
