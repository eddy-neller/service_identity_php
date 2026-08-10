<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Catalog\DisplayProduct\DisplayProductQuery;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use LogicException;

final readonly class ProductGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private ProductResourcePresenter $productResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $productId = $uriVariables['id'] ?? null;

        if (!is_string($productId) || '' === $productId) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $output = $this->queryBus->dispatch(new DisplayProductQuery($productId));

        return $this->productResourcePresenter->toResource($output);
    }
}
