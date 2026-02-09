<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel;

use App\Domain\Shop\Customer\Model\Address;
use App\Domain\Shop\Customer\Model\Customer;

final readonly class CustomerItem
{
    /**
     * @param list<Address> $addresses
     */
    public function __construct(
        public Customer $customer,
        public array $addresses,
    ) {
    }
}
