<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Ordering\DisplayMyCart;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Service\CartItemFactory;

final readonly class DisplayMyCartQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $repository,
        private CartItemFactory $factory,
    ) {
    }

    public function handle(DisplayMyCartQuery $query): DisplayMyCartOutput
    {
        return new DisplayMyCartOutput($this->factory->create($this->repository->findByOwner($query->customerId)));
    }
}
