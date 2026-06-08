<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayCustomer;

use App\Application\Shop\ReadModel\Customer\CustomerItem;

final readonly class DisplayCustomerOutput
{
    public function __construct(
        public CustomerItem $customerItem,
    ) {
    }
}
