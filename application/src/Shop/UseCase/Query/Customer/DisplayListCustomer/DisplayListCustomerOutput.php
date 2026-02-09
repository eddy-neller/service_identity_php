<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Domain\Shop\Customer\Model\Customer;

final readonly class DisplayListCustomerOutput
{
    /**
     * @param list<Customer> $customers
     */
    public function __construct(
        public array $customers,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
