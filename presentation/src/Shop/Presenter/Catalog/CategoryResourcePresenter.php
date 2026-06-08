<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Presenter\Catalog;

use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Domain\Shop\Catalog\Model\Category as DomainCategory;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;

final readonly class CategoryResourcePresenter
{
    public function toResource(CategoryItem $categoryItem): CategoryResource
    {
        $resource = $this->mapCategory($categoryItem->category);

        $resource->parent = null === $categoryItem->parent ? null : $this->mapCategory($categoryItem->parent);
        $resource->children = null === $categoryItem->children ? null : array_map(
            fn (DomainCategory $child): CategoryResource => $this->mapCategory($child),
            $categoryItem->children,
        );

        return $resource;
    }

    public function toSummaryResource(DomainCategory $category): CategoryResource
    {
        return $this->mapCategory($category);
    }

    /**
     * Flat mapping to prevent parent/children recursion in list/get payloads.
     */
    private function mapCategory(DomainCategory $category): CategoryResource
    {
        $resource = new CategoryResource();

        $resource->id = $category->getId()->toString();
        $resource->title = $category->getTitle()->toString();
        $resource->description = $category->getDescription()?->toString();
        $resource->nbProduct = $category->getProductCount();
        $resource->slug = $category->getSlug()->toString();
        $resource->level = $category->getLevel();
        $resource->hasChildren = $category->hasChildren();
        $resource->createdAt = $category->getCreatedAt();
        $resource->updatedAt = $category->getUpdatedAt();

        return $resource;
    }
}
