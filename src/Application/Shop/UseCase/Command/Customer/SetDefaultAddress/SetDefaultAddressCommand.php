<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\SetDefaultAddress;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class SetDefaultAddressCommand implements CommandInterface
{
    public function __construct(
        public string $addressId,
        public string $ownerId,
    ) {
    }
}
