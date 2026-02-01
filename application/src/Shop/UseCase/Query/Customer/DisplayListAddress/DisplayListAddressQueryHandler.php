<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;

final readonly class DisplayListAddressQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListAddressQuery $query): DisplayListAddressOutput
    {
        $addressList = $this->repository->listByOwner(
            ownerId: $query->ownerId,
            pagination: $query->pagination,
            orderBy: $query->orderBy,
            filters: $query->filters,
        );

        return new DisplayListAddressOutput(
            addresses: $addressList->addresses,
            totalItems: $addressList->totalItems,
            totalPages: $addressList->totalPages,
        );
    }
}
