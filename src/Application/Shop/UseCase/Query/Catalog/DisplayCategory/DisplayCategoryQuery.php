<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayCategory;

use App\Application\Shared\CQRS\Query\QueryInterface;

final readonly class DisplayCategoryQuery implements QueryInterface
{
    public function __construct(
        public string $categoryId,
    ) {
    }
}
