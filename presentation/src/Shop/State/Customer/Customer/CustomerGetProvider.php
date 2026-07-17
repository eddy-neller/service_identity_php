<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use LogicException;

final readonly class CustomerGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CustomerResourcePresenter $customerResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerResource
    {
        $customerId = $uriVariables['id'] ?? null;

        if (!is_string($customerId) || '' === $customerId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->queryBus->dispatch(new DisplayCustomerQuery($customerId));

        return $this->customerResourcePresenter->toResource($output);
    }
}
