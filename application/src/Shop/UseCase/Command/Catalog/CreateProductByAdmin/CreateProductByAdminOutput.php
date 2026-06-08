<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin;

use App\Application\Shop\ReadModel\Catalog\ProductItem;

final readonly class CreateProductByAdminOutput
{
    public function __construct(
        public ProductItem $productItem,
    ) {
    }
}
