<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Customer\DisplayListAddress;

use App\Application\Shared\CQRS\Query\QueryInterface;

final readonly class DisplayListAddressQuery implements QueryInterface
{
    public function __construct(
        public string $ownerId,
        public ?string $page,
        public ?string $itemsPerPage,
        public array $orderBy,
        public array $filters,
    ) {
    }
}
