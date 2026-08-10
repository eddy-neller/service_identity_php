<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;

interface CustomerRepositoryInterface
{
    public const array SORT_FIELDS = ['username', 'status', 'createdAt'];

    public function nextIdentity(): CustomerId;

    /**
     * @return array{items: list<Customer>, totalItems: int, totalPages: int}
     */
    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): array;

    public function save(Customer $customer): void;

    public function delete(Customer $customer): void;

    public function findById(CustomerId $id): ?Customer;

    public function findByUserAccountId(UserAccountId $userAccountId): ?Customer;
}
