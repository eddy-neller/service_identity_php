<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer;

use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Customer\ValueObject\CustomerStatus;

final readonly class DisplayMyCustomerOutput
{
    public function __construct(
        public CustomerId $customerId,
        public CustomerStatus $status,
    ) {
    }
}
