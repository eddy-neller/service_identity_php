<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\CreateProductByAdmin\CreateProductByAdminCommand;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Dto\Catalog\Product\ProductPostInput;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use LogicException;

final readonly class ProductPostProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ProductResourcePresenter $productResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        if (!$data instanceof ProductPostInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $command = new CreateProductByAdminCommand(
            title: $data->title,
            subtitle: $data->subtitle,
            description: $data->description,
            price: $data->price,
            categoryId: CategoryId::fromString($data->category->id),
        );

        $output = $this->commandBus->dispatch($command);

        return $this->productResourcePresenter->toResource($output);
    }
}
