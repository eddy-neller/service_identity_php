<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Dto\Customer\Address\AddressPostInput;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use LogicException;

final readonly class AddressPostProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentCustomerResolver $customerResolver,
        private AddressResourcePresenter $presenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AddressResource
    {
        if (!$data instanceof AddressPostInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new CreateAddressCommand(
            ownerId: $this->customerResolver->resolve(),
            label: $data->name,
            firstname: $data->firstname,
            lastname: $data->lastname,
            company: $data->company,
            street: $data->address,
            zipCode: $data->zip,
            city: $data->city,
            country: $data->country,
            phone: $data->phone,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->presenter->toResource($output);
    }
}
