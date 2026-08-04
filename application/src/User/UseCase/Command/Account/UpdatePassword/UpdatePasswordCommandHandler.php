<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\UpdatePassword;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\Security\InvalidCurrentPasswordException;
use App\Domain\User\Exception\Security\SamePasswordException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Security\HashedPassword;

final readonly class UpdatePasswordCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(UpdatePasswordCommand $command): void
    {
        $userId = UserId::fromString($command->userId);
        $hashedPassword = HashedPassword::fromString($this->passwordHasher->hash($command->newPassword));

        $user = $this->transactional->transactional(function () use ($userId, $hashedPassword, $command): User {
            $user = $this->repository->findById($userId);

            if (null === $user) {
                throw new UserNotFoundException();
            }

            $currentPasswordHash = $user->getPassword()->toString();
            if (!$this->passwordHasher->verify($currentPasswordHash, $command->currentPassword)) {
                throw new InvalidCurrentPasswordException();
            }

            if ($this->passwordHasher->verify($currentPasswordHash, $command->newPassword)) {
                throw new SamePasswordException();
            }

            $user->changePassword($hashedPassword, $this->clock->now());

            $this->repository->save($user);

            return $user;
        });

        $this->eventDispatcher->dispatchAll($user->releaseEvents());
    }
}
