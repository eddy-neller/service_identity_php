<?php

declare(strict_types=1);

namespace App\Presentation\User\State\UserManagement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Account\UpdateAvatar\UpdateAvatarCommand;
use App\Presentation\Shared\Adapter\SymfonyFileAdapter;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserManagement\UserAvatarInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use LogicException;

/**
 * Processor pour gérer l'upload d'avatar d'un utilisateur par un admin.
 */
final readonly class UserAvatarProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        if (!$data instanceof UserAvatarInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (null === $data->avatarFile) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $userId = $uriVariables['id'] ?? null;

        if (!is_string($userId) || '' === $userId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $avatarFile = new SymfonyFileAdapter($data->avatarFile);

        $command = new UpdateAvatarCommand(
            userId: $userId,
            avatarFile: $avatarFile,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->userResourcePresenter->toResource($output);
    }
}
