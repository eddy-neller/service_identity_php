<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Customer\DisplayCustomer\DisplayCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
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
        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $customerId = CustomerId::fromString($uriVariables['id']);
        $output = $this->queryBus->dispatch(new DisplayCustomerQuery($customerId));

        return $this->customerResourcePresenter->toResource($output);
    }
}
