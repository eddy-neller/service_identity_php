<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayAddress;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\Customer\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayAddressQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayAddressQuery $query): AddressItem
    {
        $address = $this->repository->findById(AddressId::fromString($query->addressId));

        if (null === $address || !$address->belongsTo(CustomerId::fromString($query->ownerId))) {
            throw new AddressNotFoundException();
        }

        return AddressItem::fromAddress($address);
    }
}
