<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Shared;

use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\User\Security\UserMeSecurityTrait;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentCustomerResolver
{
    use UserMeSecurityTrait;

    public function __construct(
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }

    public function resolve(): CustomerId
    {
        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $output = $this->queryBus->dispatch(new DisplayMyCustomerQuery(
            UserAccountId::fromString($userId->toString()),
        ));

        return $output->customerId;
    }
}
