<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\Service\CartItemFactory;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\ValueObject\CartLineQuantityChange;

final readonly class UpdateCartLineCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private CartItemFactory $cartItemFactory,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(UpdateCartLineCommand $command): CartItem
    {
        $customerId = CustomerId::fromString($command->customerId);
        $productId = ProductId::fromString($command->productId);
        $quantity = CartLineQuantityChange::fromInt($command->quantity);

        $cart = $this->transactional->transactional(function () use ($customerId, $productId, $quantity): Cart {
            $cart = $this->repository->findByOwnerForUpdate($customerId)
                ?? throw new CartLineNotFoundException();

            $cart->changeLineQuantity($productId, $quantity, $this->clock->now());

            $this->repository->save($cart);

            return $cart;
        });

        return $this->cartItemFactory->create($cart);
    }
}
