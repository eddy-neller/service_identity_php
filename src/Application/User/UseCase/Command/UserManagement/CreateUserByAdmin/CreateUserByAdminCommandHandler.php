<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UserManagement\CreateUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\Port\UserUniquenessCheckerInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Access\RoleSet;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Lifecycle\UserStatus;
use App\Domain\User\ValueObject\Profile\Firstname;
use App\Domain\User\ValueObject\Profile\Lastname;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;

final readonly class CreateUserByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private UserUniquenessCheckerInterface $uniquenessChecker,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(CreateUserByAdminCommand $command): UserItem
    {
        $username = Username::fromString($command->username);
        $email = EmailAddress::fromString($command->email);

        // Cf. RegisterUserCommandHandler : le contrôle passe avant le hash pour ne pas
        // payer bcrypt sur une création qui part en 409, l'index unique faisant foi.
        $this->uniquenessChecker->ensureEmailAndUsernameAvailable($email, $username);

        $hashedPassword = HashedPassword::fromString($this->passwordHasher->hash($command->plainPassword));
        $roles = RoleSet::fromArray($command->roles);
        $status = UserStatus::fromInt($command->status);
        $firstname = $command->firstname ? Firstname::fromString($command->firstname) : null;
        $lastname = $command->lastname ? Lastname::fromString($command->lastname) : null;

        $user = User::createByAdmin(
            id: $this->repository->nextIdentity(),
            username: $username,
            email: $email,
            password: $hashedPassword,
            roles: $roles,
            status: $status,
            now: $this->clock->now(),
            firstname: $firstname,
            lastname: $lastname,
            preferences: Preferences::create(),
        );

        $user = $this->transactional->transactional(function () use ($user): User {
            $this->repository->add($user);
            $this->eventBus->publishAll($user->releaseEvents());

            return $user;
        });

        return UserItem::fromUser($user);
    }
}
