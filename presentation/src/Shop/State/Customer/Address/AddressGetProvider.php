<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class AddressGetProvider implements ProviderInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private QueryBusInterface $queryBus,
        private AddressResourcePresenter $presenter,
        private Security $security,
    ) {
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $rawId = $uriVariables['id'] ?? null;
        if (!is_string($rawId) || '' === $rawId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $customerOutput = $this->queryBus->dispatch(new DisplayCustomerQuery(
            userAccountId: UserAccountId::fromString($userId->toString()),
        ));

        $output = $this->queryBus->dispatch(new DisplayAddressQuery(
            addressId: AddressId::fromString($rawId),
            ownerId: $customerOutput->customerId,
        ));

        return $this->presenter->toResource($output->addressItem->address);
    }
}
