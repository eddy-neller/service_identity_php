<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\CreateCustomer;

use App\Domain\Shop\Customer\Model\Customer;

final readonly class CreateCustomerOutput
{
    public function __construct(
        public Customer $customer,
    ) {
    }
}
