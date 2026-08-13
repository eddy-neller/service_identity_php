<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\Account\UpdateAvatar;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\DomainEventBusInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\AvatarImageValidatorInterface;
use App\Application\User\Port\AvatarStorageInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\Identity\UserId;
use Exception;

final readonly class UpdateAvatarCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private AvatarImageValidatorInterface $avatarImageValidator,
        private AvatarStorageInterface $avatarStorage,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private DomainEventBusInterface $eventBus,
    ) {
    }

    public function handle(UpdateAvatarCommand $command): UserItem
    {
        $userId = UserId::fromString($command->userId);
        $this->avatarImageValidator->validate($command->avatarFile);

        $avatar = $this->avatarStorage->store($command->avatarFile);

        try {
            $update = $this->transactional->transactional(function () use ($userId, $avatar): array {
                $user = $this->repository->findById($userId);

                if (null === $user) {
                    throw new UserNotFoundException();
                }

                $previousAvatarName = $user->getAvatarName();
                $user->updateAvatar($avatar, $this->clock->now());

                $this->repository->save($user);
                $this->eventBus->publishAll($user->releaseEvents());

                return [
                    'previousAvatarName' => $previousAvatarName,
                    'user' => $user,
                ];
            });
        } catch (Exception $exception) {
            $this->avatarStorage->delete($avatar);

            throw $exception;
        }

        if (null !== $update['previousAvatarName']) {
            $this->avatarStorage->delete($update['previousAvatarName']);
        }

        return UserItem::fromUser($update['user']);
    }
}
