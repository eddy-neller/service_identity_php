<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayListAddressQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayListAddressQuery $query): AddressList
    {
        $pagination = Pagination::fromRaw($query->page, $query->itemsPerPage);

        $result = $this->repository->listByOwner(
            ownerId: CustomerId::fromString($query->ownerId),
            page: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
            orderBy: $query->orderBy,
            filters: $query->filters,
        );

        return new AddressList(
            items: array_map(static fn (Address $address): AddressItem => AddressItem::fromAddress($address), $result['items']),
            totalItems: $result['totalItems'],
            totalPages: $result['totalPages'],
        );
    }
}
