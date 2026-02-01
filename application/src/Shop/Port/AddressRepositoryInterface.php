<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\ReadModel\AddressList;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

interface AddressRepositoryInterface
{
    public function nextIdentity(): AddressId;

    public function save(Address $address): void;

    public function delete(Address $address): void;

    public function findById(AddressId $id): ?Address;

    public function listByOwner(CustomerId $ownerId, Pagination $pagination, array $orderBy, array $filters): AddressList;
}
