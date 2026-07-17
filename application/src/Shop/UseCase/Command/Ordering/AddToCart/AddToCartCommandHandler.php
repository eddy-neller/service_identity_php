<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\AddToCart;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\Service\CartItemFactory;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\ValueObject\CartLineQuantity;

final readonly class AddToCartCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductRepositoryInterface $productRepository,
        private CartItemFactory $cartItemFactory,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(AddToCartCommand $command): CartItem
    {
        $productId = ProductId::fromString($command->productId);
        $customerId = CustomerId::fromString($command->customerId);
        $quantity = CartLineQuantity::fromInt($command->quantity);
        $cartId = $this->cartRepository->nextIdentity();
        $cartLineId = $this->cartRepository->nextLineIdentity();

        $cart = $this->transactional->transactional(function () use ($productId, $customerId, $cartId, $cartLineId, $quantity): Cart {
            if (null === $this->productRepository->findById($productId)) {
                throw new ProductNotFoundException();
            }

            $now = $this->clock->now();

            $cart = $this->cartRepository->findByOwnerForUpdate($customerId)
                ?? Cart::create($cartId, $customerId, $now);

            $cart->addLine(
                $cartLineId,
                $productId,
                $quantity,
                $now,
            );
            $this->cartRepository->save($cart);

            return $cart;
        });

        return $this->cartItemFactory->create($cart);
    }
}
