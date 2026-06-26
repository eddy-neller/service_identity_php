<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Firstname;
use App\Domain\User\ValueObject\Lastname;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\Username;

final readonly class UpdateUserByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private UserUniquenessCheckerInterface $uniquenessChecker,
    ) {
    }

    public function handle(UpdateUserByAdminCommand $command): UserItem
    {
        $user = $this->repository->findById($command->userId);

        if (null === $user) {
            throw new UserNotFoundException();
        }

        if (null !== $command->email) {
            $this->uniquenessChecker->ensureEmailAvailable(EmailAddress::fromString($command->email), $user->getId());
        }

        if (null !== $command->username) {
            $this->uniquenessChecker->ensureUsernameAvailable(Username::fromString($command->username), $user->getId());
        }

        return $this->transactional->transactional(function () use ($user, $command): UserItem {
            $now = $this->clock->now();
            $hashedPassword = null;
            if (null !== $command->plainPassword && '' !== trim($command->plainPassword)) {
                $hashedPassword = $this->passwordHasher->hash($command->plainPassword);
            }

            $user->updateByAdmin(
                now: $now,
                username: $command->username ? Username::fromString($command->username) : null,
                email: $command->email ? EmailAddress::fromString($command->email) : null,
                firstname: $command->firstname ? Firstname::fromString($command->firstname) : null,
                lastname: $command->lastname ? Lastname::fromString($command->lastname) : null,
                roles: $command->roles ? RoleSet::fromArray($command->roles) : null,
                status: $command->status ? UserStatus::fromInt($command->status) : null,
                password: $hashedPassword,
            );

            $this->repository->save($user);

            return UserItem::fromUser($user);
        });
    }
}
