<?php

declare(strict_types=1);

namespace App\Application\Shop\Service;

use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\ReadModel\Ordering\CartLineItem;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\Model\CartLine;

final readonly class CartItemFactory
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductImageUrlResolverInterface $imageUrlResolver,
    ) {
    }

    public function create(?Cart $cart): CartItem
    {
        if (null === $cart) {
            return new CartItem(null, [], 0, 0.0, 'EUR', null, null);
        }

        $items = [];
        $totalQuantity = 0;
        $subtotalCents = 0;
        $products = [];

        $productIds = array_map(
            static fn (CartLine $line) => $line->getProductId(),
            $cart->getLines(),
        );

        foreach ($this->productRepository->findByIds($productIds) as $product) {
            $products[$product->getId()->toString()] = $product;
        }

        foreach ($cart->getLines() as $line) {
            $product = $products[$line->getProductId()->toString()] ?? null;
            if (null === $product) {
                continue;
            }

            $unitPrice = $product->getPrice()->amount();
            $lineTotal = $unitPrice * $line->getQuantity();
            $totalQuantity += $line->getQuantity();
            $subtotalCents += $lineTotal;

            $items[] = new CartLineItem(
                id: $line->getId()->toString(),
                productId: $product->getId()->toString(),
                productTitle: $product->getTitle()->toString(),
                productSlug: $product->getSlug()->toString(),
                imageUrl: $this->imageUrlResolver->resolve($product->getImageName()),
                unitPrice: $unitPrice / 100,
                quantity: $line->getQuantity(),
                lineTotal: $lineTotal / 100,
            );
        }

        return new CartItem(
            id: $cart->getId()->toString(),
            items: $items,
            totalQuantity: $totalQuantity,
            subtotal: $subtotalCents / 100,
            currency: 'EUR',
            createdAt: $cart->getCreatedAt(),
            updatedAt: $cart->getUpdatedAt(),
        );
    }
}
