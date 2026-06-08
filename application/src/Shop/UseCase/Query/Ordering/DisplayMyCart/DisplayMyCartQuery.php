<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart;

use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class DisplayMyCartQuery implements QueryInterface
{
    public function __construct(public CustomerId $customerId)
    {
    }
}
