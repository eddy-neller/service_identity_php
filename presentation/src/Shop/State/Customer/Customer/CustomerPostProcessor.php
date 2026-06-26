<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Customer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateCustomer\CreateCustomerCommand;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\CustomerResource;
use App\Presentation\Shop\Dto\Customer\CustomerPostInput;
use App\Presentation\Shop\Presenter\Customer\CustomerResourcePresenter;
use LogicException;

final readonly class CustomerPostProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CustomerResourcePresenter $customerResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerResource
    {
        if (!$data instanceof CustomerPostInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $userAccountId = UserAccountId::fromString($data->userAccountId);

        $output = $this->commandBus->dispatch(new CreateCustomerCommand($userAccountId));

        return $this->customerResourcePresenter->toSummaryResource($output);
    }
}
