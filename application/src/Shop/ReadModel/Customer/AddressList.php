<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel\Customer;

final readonly class AddressList
{
    public function __construct(
        public array $addresses,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
