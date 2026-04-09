<?php

declare(strict_types=1);

namespace App\Presentation\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\CreateUserByAdmin\CreateUserByAdminCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Dto\UserPostInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use LogicException;

final readonly class UserPostProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        if (!$data instanceof UserPostInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new CreateUserByAdminCommand(
            email: $data->email,
            username: $data->username,
            plainPassword: $data->password,
            roles: $data->roles,
            status: $data->status,
            firstname: $data->firstname,
            lastname: $data->lastname,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->userResourcePresenter->toResource($output->user);
    }
}
