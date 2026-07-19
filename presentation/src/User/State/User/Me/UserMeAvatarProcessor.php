<?php

declare(strict_types=1);

namespace App\Presentation\User\State\User\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\UpdateAvatar\UpdateAvatarCommand;
use App\Presentation\Shared\Adapter\SymfonyFileAdapter;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\User\Me\UserMeAvatarInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class UserMeAvatarProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private Security $security,
        private CommandBusInterface $commandBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof UserMeAvatarInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (null === $data->avatarFile) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $avatarFile = new SymfonyFileAdapter($data->avatarFile);

        $command = new UpdateAvatarCommand(
            userId: $userId,
            avatarFile: $avatarFile,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->userResourcePresenter->toResource($output);
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }
}
