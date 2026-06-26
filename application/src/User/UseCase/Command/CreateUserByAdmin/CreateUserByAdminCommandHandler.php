<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\CreateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Firstname;
use App\Domain\User\ValueObject\Lastname;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\Username;

final readonly class CreateUserByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private UserUniquenessCheckerInterface $uniquenessChecker,
    ) {
    }

    public function handle(CreateUserByAdminCommand $command): UserItem
    {
        return $this->transactional->transactional(function () use ($command): UserItem {
            $now = $this->clock->now();
            $userId = $this->repository->nextIdentity();
            $username = Username::fromString($command->username);
            $email = EmailAddress::fromString($command->email);

            $this->uniquenessChecker->ensureEmailAndUsernameAvailable($email, $username);

            $hashedPassword = $this->passwordHasher->hash($command->plainPassword);
            $roles = RoleSet::fromArray($command->roles);
            $status = UserStatus::fromInt($command->status);

            $user = User::createByAdmin(
                id: $userId,
                username: $username,
                email: $email,
                password: $hashedPassword,
                roles: $roles,
                status: $status,
                now: $now,
                firstname: $command->firstname ? Firstname::fromString($command->firstname) : null,
                lastname: $command->lastname ? Lastname::fromString($command->lastname) : null,
                preferences: new Preferences(),
            );

            $this->repository->save($user);

            return UserItem::fromUser($user);
        });
    }
}
