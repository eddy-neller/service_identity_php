<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\AddToCart;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\Service\CartItemFactory;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Ordering\Model\Cart;

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

    public function handle(AddToCartCommand $command): AddToCartOutput
    {
        return $this->transactional->transactional(function () use ($command): AddToCartOutput {
            if (null === $this->productRepository->findById($command->productId)) {
                throw new ProductNotFoundException();
            }

            $cart = $this->cartRepository->findByOwnerForUpdate($command->customerId)
                ?? Cart::create($this->cartRepository->nextIdentity(), $command->customerId, $this->clock->now());
            $cart->addLine(
                $this->cartRepository->nextLineIdentity(),
                $command->productId,
                $command->quantity,
                $this->clock->now(),
            );
            $this->cartRepository->save($cart);

            return new AddToCartOutput($this->cartItemFactory->create($cart));
        });
    }
}
