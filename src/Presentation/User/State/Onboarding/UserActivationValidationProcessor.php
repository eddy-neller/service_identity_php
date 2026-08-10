<?php

declare(strict_types=1);

namespace App\Presentation\User\State\Onboarding;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Onboarding\ValidateActivation\ValidateActivationCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\Onboarding\UserActivationValidationInput;
use LogicException;

final readonly class UserActivationValidationProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof UserActivationValidationInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new ValidateActivationCommand($data->token);
        $this->commandBus->dispatch($command);
    }
}
