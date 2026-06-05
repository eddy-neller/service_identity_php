<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class SetDefaultAddressCommand implements CommandInterface
{
    public function __construct(
        public AddressId $addressId,
        public CustomerId $ownerId,
    ) {
    }
}
