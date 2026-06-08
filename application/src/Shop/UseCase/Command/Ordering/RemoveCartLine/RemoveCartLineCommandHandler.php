<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\RemoveCartLine;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;

final readonly class RemoveCartLineCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(RemoveCartLineCommand $command): void
    {
        $this->transactional->transactional(function () use ($command): void {
            $cart = $this->repository->findByOwnerForUpdate($command->customerId)
                ?? throw new CartLineNotFoundException();
            $cart->removeLine($command->productId, $this->clock->now());

            $this->repository->save($cart);
        });
    }
}
