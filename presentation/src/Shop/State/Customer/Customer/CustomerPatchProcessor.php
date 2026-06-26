<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Customer\DisableCustomer\DisableCustomerCommand;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;
use App\Presentation\Shop\Dto\Customer\CustomerPatchInput;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use LogicException;

final readonly class CustomerPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CustomerResourcePresenter $customerResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerResource
    {
        if (!$data instanceof CustomerPatchInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $customerId = CustomerId::fromString($uriVariables['id']);
        $output = $this->commandBus->dispatch(new DisableCustomerCommand($customerId));

        return $this->customerResourcePresenter->toSummaryResource($output);
    }
}
