<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListCustomer;

use App\Application\Shared\CQRS\Query\QueryInterface;

final readonly class DisplayListCustomerQuery implements QueryInterface
{
    public function __construct(
        public ?string $page = null,
        public ?string $itemsPerPage = null,
        public array $filters = [],
        public array $orderBy = [],
    ) {
    }
}
