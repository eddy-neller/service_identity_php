<?php

declare(strict_types=1);

namespace App\Presentation\User\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\RefreshToken\RefreshTokenCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\AuthResource;
use App\Presentation\User\Dto\Auth\RefreshTokenInput;
use App\Presentation\User\Presenter\AuthResourcePresenter;
use LogicException;

final readonly class RefreshTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private AuthResourcePresenter $presenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthResource
    {
        if (!$data instanceof RefreshTokenInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $tokens = $this->commandBus->dispatch(new RefreshTokenCommand($data->refreshToken));

        return $this->presenter->toResource($tokens);
    }
}
