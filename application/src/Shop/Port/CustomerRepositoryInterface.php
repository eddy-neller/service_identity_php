<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

use App\Domain\Shop\Customer\Model\Customer;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\UserAccountId;

interface CustomerRepositoryInterface
{
    public function nextIdentity(): CustomerId;

    public function save(Customer $customer): void;

    public function findByUserAccountId(UserAccountId $userAccountId): ?Customer;
}
