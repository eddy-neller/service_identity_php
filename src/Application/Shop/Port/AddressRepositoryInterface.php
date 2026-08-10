<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

interface AddressRepositoryInterface
{
    public const array SORT_FIELDS = ['name', 'city', 'country', 'createdAt'];

    public function nextIdentity(): AddressId;

    public function save(Address $address): void;

    public function delete(Address $address): void;

    public function findById(AddressId $id): ?Address;

    public function countByOwnerForUpdate(CustomerId $ownerId): int;

    public function hasDefaultForOwner(CustomerId $ownerId): bool;

    public function unsetDefaultForOwner(CustomerId $ownerId): void;

    public function findDefaultReplacementForOwner(CustomerId $ownerId, AddressId $excludedId): ?Address;

    /**
     * @return array{items: list<Address>, totalItems: int, totalPages: int}
     */
    public function listByOwner(CustomerId $ownerId, int $page, int $itemsPerPage, array $orderBy, array $filters): array;
}
