<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UserManagement\DeleteUserByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\Identity\UserId;

final readonly class DeleteUserByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(DeleteUserByAdminCommand $command): void
    {
        $userId = UserId::fromString($command->userId);

        $this->transactional->transactional(function () use ($userId): void {
            $user = $this->repository->findById($userId);

            if (null === $user) {
                throw new UserNotFoundException();
            }

            $user->deleteByAdmin($this->clock->now());

            $this->repository->delete($user);
            $this->eventBus->publishAll($user->releaseEvents());
        });
    }
}
