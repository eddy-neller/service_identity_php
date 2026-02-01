<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayAddress;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\AddressRepositoryInterface;
use App\Application\Shop\ReadModel\AddressItem;
use App\Domain\Shop\Customer\Exception\AddressNotFoundException;

final readonly class DisplayAddressQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private AddressRepositoryInterface $repository,
    ) {
    }

    public function handle(DisplayAddressQuery $query): DisplayAddressOutput
    {
        $address = $this->repository->findById($query->addressId);

        if (null === $address || !$address->belongsTo($query->ownerId)) {
            throw new AddressNotFoundException();
        }

        return new DisplayAddressOutput(new AddressItem($address));
    }
}
