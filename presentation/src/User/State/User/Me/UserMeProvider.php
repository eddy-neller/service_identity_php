<?php

declare(strict_types=1);

namespace App\Presentation\User\State\User\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\User\UseCase\Query\DisplayUser\DisplayUserQuery;
use App\Presentation\User\ApiResource\UserResource;
use App\Presentation\User\Presenter\UserResourcePresenter;
use App\Presentation\User\Security\UserMeSecurityTrait;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class UserMeProvider implements ProviderInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private Security $security,
        private QueryBusInterface $queryBus,
        private UserResourcePresenter $userResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        $currentUser = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($currentUser);

        $query = new DisplayUserQuery($userId);

        $output = $this->queryBus->dispatch($query);

        return $this->userResourcePresenter->toResource($output);
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }
}
