<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\UpdateAddress;

use App\Application\Shop\ReadModel\AddressItem;

final readonly class UpdateAddressOutput
{
    public function __construct(
        public AddressItem $addressItem,
    ) {
    }
}
