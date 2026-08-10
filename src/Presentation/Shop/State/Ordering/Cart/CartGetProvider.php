<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart\DisplayMyCartQuery;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;
use App\Presentation\Shop\Presenter\Ordering\CartResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;

final readonly class CartGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CurrentCustomerResolver $customerResolver,
        private CartResourcePresenter $presenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CartResource
    {
        $output = $this->queryBus->dispatch(new DisplayMyCartQuery($this->customerResolver->resolve()));

        return $this->presenter->toResource($output);
    }
}
