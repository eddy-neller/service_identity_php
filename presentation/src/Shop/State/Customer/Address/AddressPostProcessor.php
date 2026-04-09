<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Command\Customer\CreateAddress\CreateAddressCommand;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Customer\AddressResource;
use App\Presentation\Shop\Dto\Customer\Address\AddressPostInput;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class AddressPostProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private AddressResourcePresenter $presenter,
        private Security $security,
    ) {
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AddressResource
    {
        if (!$data instanceof AddressPostInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $customerOutput = $this->queryBus->dispatch(new DisplayMyCustomerQuery(
            userAccountId: UserAccountId::fromString($userId->toString()),
        ));

        $command = new CreateAddressCommand(
            ownerId: $customerOutput->customerId,
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

        return $this->presenter->toResource($output->addressItem->address);
    }
}
