<?php

declare(strict_types=1);

namespace App\Presentation\User\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\UseCase\Command\Auth\Logout\LogoutCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Dto\Auth\LogoutInput;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class LogoutProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof LogoutInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);

        $this->commandBus->dispatch(new LogoutCommand($userId, $data->refreshToken));
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }
}
