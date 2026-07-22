<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UserManagement\UpdateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Profile\Firstname;
use App\Domain\User\ValueObject\Profile\Lastname;
use App\Domain\User\ValueObject\Security\HashedPassword;

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
        $userId = UserId::fromString($command->userId);
        $email = null !== $command->email ? EmailAddress::fromString($command->email) : null;
        $username = null !== $command->username ? Username::fromString($command->username) : null;
        $firstname = $command->firstname ? Firstname::fromString($command->firstname) : null;
        $lastname = $command->lastname ? Lastname::fromString($command->lastname) : null;
        $roles = $command->roles ? RoleSet::fromArray($command->roles) : null;
        $status = null !== $command->status ? UserStatus::fromInt($command->status) : null;
        $hashedPassword = null !== $command->plainPassword && '' !== trim($command->plainPassword)
            ? HashedPassword::fromString($this->passwordHasher->hash($command->plainPassword))
            : null;

        $user = $this->transactional->transactional(function () use ($userId, $email, $username, $firstname, $lastname, $roles, $status, $hashedPassword): User {
            $user = $this->repository->findById($userId);

            if (null === $user) {
                throw new UserNotFoundException();
            }

            if (null !== $email) {
                $this->uniquenessChecker->ensureEmailAvailable($email, $user->getId());
            }

            if (null !== $username) {
                $this->uniquenessChecker->ensureUsernameAvailable($username, $user->getId());
            }

            $user->updateByAdmin(
                now: $this->clock->now(),
                username: $username,
                email: $email,
                firstname: $firstname,
                lastname: $lastname,
                roles: $roles,
                status: $status,
                password: $hashedPassword,
            );

            $this->repository->save($user);

            return $user;
        });

        return UserItem::fromUser($user);
    }
}
