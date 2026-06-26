<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Category;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Shared\CQRS\Query\QueryBusInterface;
use App\Application\Shop\UseCase\Query\Catalog\DisplayCategory\DisplayCategoryQuery;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use LogicException;

final readonly class CategoryGetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CategoryResourcePresenter $categoryResourcePresenter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CategoryResource
    {
        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $categoryId = CategoryId::fromString($uriVariables['id']);
        $output = $this->queryBus->dispatch(new DisplayCategoryQuery($categoryId));

        return $this->categoryResourcePresenter->toResource($output);
    }
}
