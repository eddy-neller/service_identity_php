<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\UpdateProductByAdmin\UpdateProductByAdminCommand;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Dto\Catalog\Product\ProductPatchInput;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use LogicException;

final readonly class ProductPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ProductResourcePresenter $productResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        if (!$data instanceof ProductPatchInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $categoryId = null;
        if (null !== $data->category) {
            $categoryId = CategoryId::fromString($data->category->id);
        }

        $command = new UpdateProductByAdminCommand(
            productId: ProductId::fromString($uriVariables['id']),
            title: $data->title,
            subtitle: $data->subtitle,
            description: $data->description,
            price: $data->price,
            categoryId: $categoryId,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->productResourcePresenter->toResource($output);
    }
}
