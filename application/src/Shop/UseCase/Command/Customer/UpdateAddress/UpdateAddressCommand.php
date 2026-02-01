<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\UpdateAddress;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\Shop\Customer\ValueObject\AddressId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class UpdateAddressCommand implements CommandInterface
{
    public function __construct(
        public AddressId $addressId,
        public CustomerId $ownerId,
        public string $label,
        public string $firstname,
        public string $lastname,
        public ?string $company,
        public string $street,
        public string $zipCode,
        public string $city,
        public string $country,
        public string $phone,
    ) {
    }
}
