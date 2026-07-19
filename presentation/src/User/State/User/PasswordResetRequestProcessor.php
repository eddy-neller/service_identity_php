<?php

declare(strict_types=1);

namespace App\Presentation\User\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\User\PasswordResetRequestInput;
use LogicException;

final readonly class PasswordResetRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof PasswordResetRequestInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new RequestPasswordResetCommand($data->email);
        $this->commandBus->dispatch($command);
    }
}
