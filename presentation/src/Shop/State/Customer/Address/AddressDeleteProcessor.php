<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Customer\DeleteAddress\DeleteAddressCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use LogicException;

final readonly class AddressDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentCustomerResolver $customerResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $addressId = $uriVariables['id'] ?? null;

        if (!is_string($addressId) || '' === $addressId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $this->commandBus->dispatch(new DeleteAddressCommand(
            addressId: $addressId,
            ownerId: $this->customerResolver->resolve(),
        ));

        return null;
    }
}
