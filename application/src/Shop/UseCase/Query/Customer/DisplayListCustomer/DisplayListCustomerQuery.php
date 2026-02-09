<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Application\Shared\ReadModel\Pagination;

final readonly class DisplayListCustomerQuery implements QueryInterface
{
    public function __construct(
        public Pagination $pagination,
        public array $filters = [],
        public array $orderBy = [],
    ) {
    }
}
