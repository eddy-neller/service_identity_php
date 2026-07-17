<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayMyCustomer;

use App\Application\Shared\CQRS\Query\QueryInterface;

final readonly class DisplayMyCustomerQuery implements QueryInterface
{
    public function __construct(
        public string $userAccountId,
    ) {
    }
}
