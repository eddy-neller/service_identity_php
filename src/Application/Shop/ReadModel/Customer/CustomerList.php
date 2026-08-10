<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Customer;

final readonly class CustomerList
{
    /**
     * @param list<CustomerItem> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
