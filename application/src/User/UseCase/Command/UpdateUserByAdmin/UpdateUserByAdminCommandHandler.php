<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\Firstname;
use App\Domain\User\Identity\ValueObject\Lastname;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Security\ValueObject\RoleSet;
use App\Domain\User\Security\ValueObject\UserStatus;

final readonly class UpdateUserByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateUserByAdminCommand $command): UpdateUserByAdminOutput
    {
        $user = $this->repository->findById($command->userId);

        if (null === $user) {
            throw new UserNotFoundException();
        }

        return $this->transactional->transactional(function () use ($user, $command): UpdateUserByAdminOutput {
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

            return new UpdateUserByAdminOutput($user);
        });
    }
}
