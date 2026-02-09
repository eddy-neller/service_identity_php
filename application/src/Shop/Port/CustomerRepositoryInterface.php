<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Application\Shop\ReadModel\CustomerList;
use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;

interface CustomerRepositoryInterface
{
    public function nextIdentity(): CustomerId;

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): CustomerList;

    public function save(Customer $customer): void;

    public function delete(Customer $customer): void;

    public function findById(CustomerId $id): ?Customer;

    public function findByUserAccountId(UserAccountId $userAccountId): ?Customer;
}
