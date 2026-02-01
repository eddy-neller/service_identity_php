<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Domain\Shop\Customer\Model\Address;

final readonly class DisplayListAddressOutput
{
    /**
     * @param list<Address> $addresses
     */
    public function __construct(
        public array $addresses,
        public int $totalItems,
        public int $totalPages,
    ) {
    }
}
