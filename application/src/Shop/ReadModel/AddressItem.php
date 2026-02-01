<?php

declare(strict_types=1);

namespace App\Application\Shop\ReadModel;

use App\Domain\Shop\Customer\Model\Address;

final readonly class AddressItem
{
    public function __construct(
        public Address $address,
    ) {
    }
}
