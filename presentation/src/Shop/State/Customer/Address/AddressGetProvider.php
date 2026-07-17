<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQuery;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use LogicException;

final readonly class AddressGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CurrentCustomerResolver $customerResolver,
        private AddressResourcePresenter $presenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $addressId = $uriVariables['id'] ?? null;
        if (!is_string($addressId) || '' === $addressId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->queryBus->dispatch(new DisplayAddressQuery(
            addressId: $addressId,
            ownerId: $this->customerResolver->resolve(),
        ));

        return $this->presenter->toResource($output);
    }
}
