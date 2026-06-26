<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayAddress\DisplayAddressQuery;
use App\Domain\Shop\Customer\ValueObject\AddressId;
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
        $rawId = $uriVariables['id'] ?? null;
        if (!is_string($rawId) || '' === $rawId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->queryBus->dispatch(new DisplayAddressQuery(
            addressId: AddressId::fromString($rawId),
            ownerId: $this->customerResolver->resolve(),
        ));

        return $this->presenter->toResource($output);
    }
}
