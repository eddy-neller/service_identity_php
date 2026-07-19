<?php

declare(strict_types=1);

namespace App\Presentation\User\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\DeleteUserByAdmin\DeleteUserByAdminCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use LogicException;

final readonly class UserDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $userId = $uriVariables['id'] ?? null;

        if (!is_string($userId) || '' === $userId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $this->commandBus->dispatch(new DeleteUserByAdminCommand($userId));

        return null;
    }
}
