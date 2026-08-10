<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Ordering\RemoveCartLine\RemoveCartLineCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use LogicException;

final readonly class CartLineDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentCustomerResolver $customerResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $productId = $uriVariables['productId'] ?? null;
        if (!is_string($productId) || '' === $productId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $this->commandBus->dispatch(new RemoveCartLineCommand(
            $this->customerResolver->resolve(),
            $productId,
        ));
    }
}
