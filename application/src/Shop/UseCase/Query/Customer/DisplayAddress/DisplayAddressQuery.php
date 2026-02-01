<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayAddress;

use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayAddressQuery implements QueryInterface
{
    public function __construct(
        public AddressId $addressId,
        public CustomerId $ownerId,
    ) {
    }
}
