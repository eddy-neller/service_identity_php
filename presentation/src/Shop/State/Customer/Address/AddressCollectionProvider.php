<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Customer\Address;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer\DisplayMyCustomerQuery;
use App\Application\Shop\UseCase\Query\Customer\DisplayListAddress\DisplayListAddressQuery;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;
use App\Presentation\Shop\Presenter\Customer\AddressResourcePresenter;
use App\Presentation\User\Security\UserMeSecurityTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressCollectionProvider implements ProviderInterface
{
    use UserMeSecurityTrait;

    public function __construct(
        private QueryBusInterface $queryBus,
        private AddressResourcePresenter $presenter,
        private Security $security,
    ) {
    }

    protected function getSecurity(): Security
    {
        return $this->security;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $pagination = Pagination::fromRaw($filters['page'] ?? null, $filters['itemsPerPage'] ?? null);
        $orderBy = is_array($filters['order'] ?? null) ? $filters['order'] : [];

        $user = $this->getCurrentUserOrThrow();
        $userId = $this->getUserIdFromAuthenticatedUser($user);
        $customerOutput = $this->queryBus->dispatch(new DisplayMyCustomerQuery(
            userAccountId: UserAccountId::fromString($userId->toString()),
        ));

        $output = $this->queryBus->dispatch(new DisplayListAddressQuery(
            ownerId: $customerOutput->customerId,
            pagination: $pagination,
            orderBy: $orderBy,
            filters: $filters,
        ));

        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('_total_items', $output->totalItems);
            $request->attributes->set('_total_pages', $output->totalPages);
        }

        $items = [];
        foreach ($output->addresses as $address) {
            $items[] = $this->presenter->toResource($address);
        }

        return $items;
    }
}
