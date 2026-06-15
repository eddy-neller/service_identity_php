<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Ordering\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine\UpdateCartLineCommand;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Ordering\CartResource;
use App\Presentation\Shop\Dto\Ordering\Cart\CartLinePatchInput;
use App\Presentation\Shop\Presenter\Ordering\CartResourcePresenter;
use App\Presentation\Shop\State\Shared\CurrentCustomerResolver;
use LogicException;

final readonly class CartLinePatchProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CurrentCustomerResolver $customerResolver,
        private CartResourcePresenter $presenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartResource
    {
        $rawProductId = $uriVariables['productId'] ?? null;
        if (!$data instanceof CartLinePatchInput || !is_string($rawProductId) || '' === $rawProductId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->commandBus->dispatch(new UpdateCartLineCommand(
            $this->customerResolver->resolve(),
            ProductId::fromString($rawProductId),
            $data->quantity,
        ));

        return $this->presenter->toResource($output->cart);
    }
}
