<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Application\Shared\CQRS\Query\QueryInterface;
use App\Application\Shared\ReadModel\Pagination;

final readonly class DisplayListAddressQuery implements QueryInterface
{
    public function __construct(
        public string $ownerId,
        public Pagination $pagination,
        public array $orderBy,
        public array $filters,
    ) {
    }
}
