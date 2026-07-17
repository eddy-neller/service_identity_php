<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayListAddressQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListAddressQuery $query): AddressList
    {
        return $this->repository->listByOwner(
            ownerId: CustomerId::fromString($query->ownerId),
            pagination: $query->pagination,
            orderBy: $query->orderBy,
            filters: $query->filters,
        );
    }
}
