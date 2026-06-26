<?php

declare(strict_types=1);

namespace App\Presentation\Shop\Presenter\Catalog;

use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;

final readonly class ProductResourcePresenter
{
    public function __construct(
        private ProductImageUrlResolverInterface $productImageUrlResolver,
        private CategoryResourcePresenter $categoryResourcePresenter,
    ) {
    }

    public function toResource(ProductItem $productItem): ProductResource
    {
        $category = $this->categoryResourcePresenter->toSummaryResource($productItem->category);

        return $this->mapProduct($productItem, $category);
    }

    private function mapProduct(ProductItem $product, CategoryResource $category): ProductResource
    {
        $resource = new ProductResource();

        $resource->id = $product->id;
        $resource->title = $product->title;
        $resource->subtitle = $product->subtitle;
        $resource->description = $product->description;
        $resource->price = $product->price;
        $resource->slug = $product->slug;
        $resource->imageUrl = $this->productImageUrlResolver->resolve($product->imageName);
        $resource->category = $category;
        $resource->createdAt = $product->createdAt;
        $resource->updatedAt = $product->updatedAt;

        return $resource;
    }
}
