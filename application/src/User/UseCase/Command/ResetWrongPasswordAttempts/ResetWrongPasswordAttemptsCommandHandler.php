<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\ResetWrongPasswordAttempts;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class ResetWrongPasswordAttemptsCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(ResetWrongPasswordAttemptsCommand $command): void
    {
        $user = $this->repository->findById(UserId::fromString($command->userId));

        if (null === $user) {
            return;
        }

        $this->transactional->transactional(function () use ($user): void {
            $now = $this->clock->now();
            $user->resetWrongPasswordAttempts($now);

            $this->repository->save($user);
        });
    }
}
