<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Shop;

use App\Application\Shop\Port\ProductImageUrlResolverInterface;

final class ProductImageUrlResolver implements ProductImageUrlResolverInterface
{
    private const string IMAGE_BASE_URL = '/uploads/images/shop/product';

    public function resolve(?string $imageName): ?string
    {
        if (null === $imageName || '' === $imageName) {
            return null;
        }

        return rtrim(self::IMAGE_BASE_URL, '/') . '/' . ltrim($imageName, '/');
    }
}
