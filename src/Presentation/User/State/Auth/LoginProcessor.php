<?php

declare(strict_types=1);

namespace App\Presentation\User\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Auth\Login\LoginCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\Auth\AuthResource;
use App\Presentation\User\Dto\Auth\LoginInput;
use App\Presentation\User\Presenter\AuthResourcePresenter;
use LogicException;

final readonly class LoginProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthResourcePresenter $presenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthResource
    {
        if (!$data instanceof LoginInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $tokens = $this->commandBus->dispatch(new LoginCommand($data->email, $data->password));

        return $this->presenter->toResource($tokens);
    }
}
