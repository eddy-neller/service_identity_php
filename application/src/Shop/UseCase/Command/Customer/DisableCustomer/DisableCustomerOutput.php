<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Domain\Shop\Customer\Model\Customer;

final readonly class DisableCustomerOutput
{
    public function __construct(
        public Customer $customer,
    ) {
    }
}
