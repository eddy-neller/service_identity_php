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
        if (!$command->avatarFile->isValid()) {
            throw new UserDomainException('Fichier avatar invalide.');
        }

        // On garde un lookup Domain ici pour appliquer l'update métier + save (events),
        // même si l'uploader récupère une référence Doctrine pour l'upload Vich.
        $user = $this->repository->findById($command->userId);

        if (null === $user) {
            throw new UserNotFoundException();
        }

        return $this->transactional->transactional(function () use ($user, $command): UserItem {
            $avatar = $this->avatarUploader->upload($command->userId, $command->avatarFile);

            $now = $this->clock->now();
            $user->updateAvatar($avatar, $now);

            $this->repository->save($user);

            return UserItem::fromUser($user);
        });
    }
}
