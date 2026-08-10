<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Customer;

final readonly class AddressList
{
    /**
     * @param list<AddressItem> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
