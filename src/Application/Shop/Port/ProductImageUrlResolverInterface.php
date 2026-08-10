<?php

declare(strict_types=1);

namespace App\Application\Shop\Port;

interface ProductImageUrlResolverInterface
{
    public function resolve(?string $imageName): ?string;
}
