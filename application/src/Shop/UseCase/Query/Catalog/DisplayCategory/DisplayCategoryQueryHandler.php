<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Query\Catalog\DisplayCategory;

use App\Application\Shared\CQRS\Query\QueryHandlerInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;

final readonly class DisplayCategoryQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function handle(DisplayCategoryQuery $query): CategoryItem
    {
        $categoryItem = $this->categoryRepository->findItemById(CategoryId::fromString($query->categoryId));

        if (null === $categoryItem) {
            throw new CategoryNotFoundException();
        }

        return $categoryItem;
    }
}
