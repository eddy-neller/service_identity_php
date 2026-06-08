<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateProductImageByAdmin;

use App\Application\Shop\ReadModel\Catalog\ProductItem;

final readonly class UpdateProductImageByAdminOutput
{
    public function __construct(
        public ProductItem $productItem,
    ) {
    }
}
