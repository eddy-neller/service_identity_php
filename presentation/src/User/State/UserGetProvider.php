<?php

declare(strict_types=1);

namespace App\Presentation\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\UseCase\Query\DisplayUser\DisplayUserQuery;
use App\Domain\User\ValueObject\UserId;
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
        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $userId = UserId::fromString($uriVariables['id']);
        $output = $this->queryBus->dispatch(new DisplayUserQuery($userId));

        return $this->userResourcePresenter->toResource($output->user);
    }
}
