<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Ordering\ClearCart;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;

final readonly class ClearCartCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
    ) {
    }

    public function handle(ClearCartCommand $command): void
    {
        $customerId = CustomerId::fromString($command->customerId);

        $this->transactional->transactional(function () use ($customerId): void {
            $cart = $this->repository->findByOwnerForUpdate($customerId);

            if (null === $cart) {
                return;
            }

            $cart->clear($this->clock->now());

            $this->repository->save($cart);
        });
    }
}
