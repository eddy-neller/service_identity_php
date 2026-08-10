<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\DeleteProductByAdmin\DeleteProductByAdminCommand;
use App\Presentation\Shared\State\PresentationErrorCode;
use LogicException;

final readonly class ProductDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $productId = $uriVariables['id'] ?? null;

        if (!is_string($productId) || '' === $productId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $this->commandBus->dispatch(new DeleteProductByAdminCommand($productId));
    }
}
