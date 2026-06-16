<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdatePassword;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\PasswordHasherInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\Security\InvalidCurrentPasswordException;
use App\Domain\User\Exception\UserNotFoundException;

final readonly class UpdatePasswordCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdatePasswordCommand $command): void
    {
        $user = $this->repository->findById($command->userId);

        if (null === $user) {
            throw new UserNotFoundException();
        }

        if (!$this->passwordHasher->verify($user->getPassword(), $command->currentPassword)) {
            throw new InvalidCurrentPasswordException();
        }

        $this->transactional->transactional(function () use ($user, $command): void {
            $now = $this->clock->now();
            $hashedPassword = $this->passwordHasher->hash($command->newPassword);
            $user->changePassword($hashedPassword, $now);

            $this->repository->save($user);
        });
    }
}
