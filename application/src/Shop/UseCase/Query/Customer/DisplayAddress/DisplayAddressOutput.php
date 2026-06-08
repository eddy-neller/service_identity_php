<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayAddress;

use App\Application\Shop\ReadModel\Customer\AddressItem;

final readonly class DisplayAddressOutput
{
    public function __construct(
        public AddressItem $addressItem,
    ) {
    }
}
