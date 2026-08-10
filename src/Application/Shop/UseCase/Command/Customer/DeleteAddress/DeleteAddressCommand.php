<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DeleteAddress;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class DeleteAddressCommand implements CommandInterface
{
    public function __construct(
        public string $addressId,
        public string $ownerId,
    ) {
    }
}
