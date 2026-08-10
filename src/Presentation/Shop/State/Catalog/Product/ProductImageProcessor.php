<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\UpdateProductImageByAdmin\UpdateProductImageByAdminCommand;
use App\Presentation\Shared\Adapter\SymfonyFileAdapter;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Dto\Catalog\Product\ProductImageInput;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use LogicException;

final readonly class ProductImageProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ProductResourcePresenter $productResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        if (!$data instanceof ProductImageInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (null === $data->imageFile) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $productId = $uriVariables['id'] ?? null;

        if (!is_string($productId) || '' === $productId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $imageFile = new SymfonyFileAdapter($data->imageFile);

        $command = new UpdateProductImageByAdminCommand(
            productId: $productId,
            imageFile: $imageFile,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->productResourcePresenter->toResource($output);
    }
}
