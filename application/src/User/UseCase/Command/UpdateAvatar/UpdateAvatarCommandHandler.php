<?php

declare(strict_types=1);

namespace App\Application\User\UseCase\Command\UpdateAvatar;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\User\Port\AvatarUploaderInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserItem;
use App\Domain\User\Exception\UserDomainException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\UserId;

final readonly class UpdateAvatarCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private AvatarUploaderInterface $avatarUploader,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateAvatarCommand $command): UserItem
    {
        $userId = UserId::fromString($command->userId);

        if (!$command->avatarFile->isValid()) {
            throw new UserDomainException('Fichier avatar invalide.');
        }

        return $this->transactional->transactional(function () use ($userId, $command): UserItem {
            $user = $this->repository->findById($userId);

            if (null === $user) {
                throw new UserNotFoundException();
            }

            $avatar = $this->avatarUploader->upload($userId, $command->avatarFile);

            $user->updateAvatar($avatar, $this->clock->now());

            $this->repository->save($user);

            return UserItem::fromUser($user);
        });
    }
}
