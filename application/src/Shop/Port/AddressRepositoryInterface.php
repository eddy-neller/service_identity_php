<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Application\Shared\ReadModel\Pagination;
use App\Application\Shop\ReadModel\Customer\AddressList;
use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

interface AddressRepositoryInterface
{
    public function nextIdentity(): AddressId;

    public function save(Address $address): void;

    public function delete(Address $address): void;

    public function findById(AddressId $id): ?Address;

    public function countByOwnerForUpdate(CustomerId $ownerId): int;

    public function hasDefaultForOwner(CustomerId $ownerId): bool;

    public function unsetDefaultForOwner(CustomerId $ownerId): void;

    public function findDefaultReplacementForOwner(CustomerId $ownerId, AddressId $excludedId): ?Address;

    public function listByOwner(CustomerId $ownerId, Pagination $pagination, array $orderBy, array $filters): AddressList;
}
