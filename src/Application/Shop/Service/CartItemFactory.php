<?php

declare(strict_types=1);

namespace App\Application\Shop\Service;

use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\ReadModel\Ordering\CartLineItem;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\Model\CartLine;
use App\Domain\Shop\Shared\ValueObject\Money;

final readonly class CartItemFactory
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductImageUrlResolverInterface $imageUrlResolver,
    ) {
    }

    public function create(?Cart $cart): CartItem
    {
        $subtotal = Money::zero();

        if (null === $cart) {
            return new CartItem(null, [], 0, $subtotal->toEuros(), $subtotal->currency(), null, null);
        }

        $items = [];
        $totalQuantity = 0;
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

            $unitPrice = $product->getPrice();
            $quantity = $line->getQuantity()->toInt();
            $lineTotal = $unitPrice->multiply($quantity);
            $totalQuantity += $quantity;
            $subtotal = $subtotal->add($lineTotal);

            $items[] = new CartLineItem(
                id: $line->getId()->toString(),
                productId: $product->getId()->toString(),
                productTitle: $product->getTitle()->toString(),
                productSlug: $product->getSlug()->toString(),
                imageUrl: $this->imageUrlResolver->resolve($product->getImageName()),
                unitPrice: $unitPrice->toEuros(),
                quantity: $quantity,
                lineTotal: $lineTotal->toEuros(),
            );
        }

        return new CartItem(
            id: $cart->getId()->toString(),
            items: $items,
            totalQuantity: $totalQuantity,
            subtotal: $subtotal->toEuros(),
            currency: $subtotal->currency(),
            createdAt: $cart->getCreatedAt(),
            updatedAt: $cart->getUpdatedAt(),
        );
    }
}
