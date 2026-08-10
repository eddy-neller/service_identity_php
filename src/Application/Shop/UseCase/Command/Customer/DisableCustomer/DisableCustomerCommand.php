<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Application\Shared\CQRS\Command\CommandInterface;

final readonly class DisableCustomerCommand implements CommandInterface
{
    public function __construct(
        public string $customerId,
    ) {
    }
}
