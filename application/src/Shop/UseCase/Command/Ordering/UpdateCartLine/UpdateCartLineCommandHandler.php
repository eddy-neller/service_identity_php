<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\Service\CartItemFactory;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;

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
        return $this->transactional->transactional(function () use ($command): CartItem {
            $cart = $this->repository->findByOwnerForUpdate($command->customerId)
                ?? throw new CartLineNotFoundException();

            $cart->changeLineQuantity($command->productId, $command->quantity, $this->clock->now());

            $this->repository->save($cart);

            return $this->cartItemFactory->create($cart);
        });
    }
}
