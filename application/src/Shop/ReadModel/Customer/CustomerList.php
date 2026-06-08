<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Customer;

use App\Domain\Shop\Customer\Model\Customer;

final readonly class CustomerList
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
