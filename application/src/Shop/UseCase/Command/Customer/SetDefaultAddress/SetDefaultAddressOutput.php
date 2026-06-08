<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shop\ReadModel\Customer\AddressItem;

final readonly class SetDefaultAddressOutput
{
    public function __construct(
        public AddressItem $addressItem,
    ) {
    }
}
