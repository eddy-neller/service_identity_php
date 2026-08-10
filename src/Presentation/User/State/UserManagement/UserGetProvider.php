<?php

declare(strict_types=1);

namespace App\Presentation\User\State\UserManagement;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\UseCase\Query\Account\DisplayUser\DisplayUserQuery;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use LogicException;

final readonly class UserGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        $userId = $uriVariables['id'] ?? null;

        if (!is_string($userId) || '' === $userId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->queryBus->dispatch(new DisplayUserQuery($userId));

        return $this->userResourcePresenter->toResource($output);
    }
}
