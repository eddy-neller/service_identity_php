<?php

declare(strict_types=1);

namespace App\Presentation\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\RequestActivationEmail\RequestActivationEmailCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\UserActivationRequestInput;
use LogicException;

final readonly class UserActivationRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof UserActivationRequestInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new RequestActivationEmailCommand($data->email);
        $this->commandBus->dispatch($command);
    }
}
