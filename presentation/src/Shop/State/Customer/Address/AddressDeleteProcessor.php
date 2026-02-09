<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Command\Customer\DeleteAddress\DeleteAddressCommand;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\User\Security\UserMeSecurityTrait;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class AddressDeleteProcessor implements ProcessorInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $rawId = $uriVariables['id'] ?? null;
        if (!is_string($rawId) || '' === $rawId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $customerOutput = $this->queryBus->dispatch(new DisplayMyCustomerQuery(
            userAccountId: UserAccountId::fromString($userId->toString()),
        ));

        $this->commandBus->dispatch(new DeleteAddressCommand(
            addressId: AddressId::fromString($rawId),
            ownerId: $customerOutput->customerId,
        ));

        return null;
    }
}
