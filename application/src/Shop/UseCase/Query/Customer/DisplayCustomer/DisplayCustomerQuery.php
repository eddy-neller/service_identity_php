<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayCustomer;

use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayCustomerQuery implements QueryInterface
{
    public function __construct(
        public CustomerId $customerId,
    ) {
    }
}
