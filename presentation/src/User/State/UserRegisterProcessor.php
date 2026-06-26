<?php

declare(strict_types=1);

namespace App\Presentation\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\RegisterUser\RegisterUserCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\UserRegisterInput;
use App\Presentation\User\Presenter\UserResourcePresenter;
use LogicException;

final readonly class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof UserRegisterInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new RegisterUserCommand(
            email: $data->email,
            username: $data->username,
            plainPassword: $data->password,
            preferences: ['lang' => $data->preferences->lang],
        );

        $output = $this->commandBus->dispatch($command);

        return $this->userResourcePresenter->toResource($output);
    }
}
