<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductByAdmin;

use App\Application\Shop\ReadModel\Catalog\ProductItem;

final readonly class UpdateProductByAdminOutput
{
    public function __construct(
        public ProductItem $productItem,
    ) {
    }
}
