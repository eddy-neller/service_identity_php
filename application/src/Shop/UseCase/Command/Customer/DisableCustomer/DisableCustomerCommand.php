<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Customer\DisableCustomer;

use App\Application\Shared\CQRS\Command\CommandInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisableCustomerCommand implements CommandInterface
{
    public function __construct(
        public CustomerId $customerId,
    ) {
    }
}
