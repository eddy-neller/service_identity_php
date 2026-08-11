<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Presenter\Ordering;

use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Presentation\Shop\ApiResource\Ordering\CartLineResource;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;

final readonly class CartResourcePresenter
{
    public function __construct(
        private ProductImageUrlResolverInterface $productImageUrlResolver,
    ) {
    }

    public function toResource(CartItem $cart): CartResource
    {
        $resource = new CartResource();
        $resource->id = $cart->id;
        $resource->totalQuantity = $cart->totalQuantity;
        $resource->subtotal = $cart->subtotal;
        $resource->currency = $cart->currency;
        $resource->createdAt = $cart->createdAt;
        $resource->updatedAt = $cart->updatedAt;

        foreach ($cart->items as $item) {
            $line = new CartLineResource();
            $line->id = $item->id;
            $line->productId = $item->productId;
            $line->productTitle = $item->productTitle;
            $line->productSlug = $item->productSlug;
            $line->imageUrl = $this->productImageUrlResolver->resolve($item->image);
            $line->unitPrice = $item->unitPrice;
            $line->quantity = $item->quantity;
            $line->lineTotal = $item->lineTotal;
            $resource->items[] = $line;
        }

        return $resource;
    }
}
