<?php

declare(strict_types=1);

namespace App\Presentation\User\State\Account\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Account\UpdatePassword\UpdatePasswordCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\Account\Me\UserMePasswordUpdateInput;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class UserMePasswordUpdateProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private Security $security,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof UserMePasswordUpdateInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);

        $command = new UpdatePasswordCommand($userId, $data->currentPassword, $data->newPassword);
        $this->commandBus->dispatch($command);

        return null;
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }
}
