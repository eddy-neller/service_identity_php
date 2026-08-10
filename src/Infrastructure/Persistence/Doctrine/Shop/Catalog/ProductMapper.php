<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Catalog;

use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Product as DomainProduct;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductImage;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use App\Infrastructure\Entity\Shop\Product as DoctrineProduct;
use Ramsey\Uuid\Uuid;

final readonly class ProductMapper
{
    public function toDomain(DoctrineProduct $entity): DomainProduct
    {
        return DomainProduct::reconstitute(
            id: ProductId::fromString($entity->getId()->toString()),
            title: ProductTitle::fromString($entity->getTitle()),
            subtitle: ProductSubtitle::fromString($entity->getSubtitle()),
            description: ProductDescription::fromString($entity->getDescription()),
            price: Money::fromInt((int) round($entity->getPrice())),
            slug: Slug::fromString($entity->getSlug()),
            categoryId: CategoryId::fromString($entity->getCategory()->getId()->toString()),
            image: ProductImage::create(
                fileName: $entity->getImageName(),
            ),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toDoctrine(DomainProduct $product, ?DoctrineProduct $entity = null): DoctrineProduct
    {
        if (null === $entity) {
            $entity = new DoctrineProduct();
            $entity->setId(Uuid::fromString($product->getId()->toString()));
        }

        $entity->setTitle($product->getTitle()->toString());
        $entity->setSubtitle($product->getSubtitle()->toString());
        $entity->setDescription($product->getDescription()->toString());
        $entity->setPrice($product->getPrice()->amount());
        $entity->setSlug($product->getSlug()->toString());
        $entity->setImageName($product->getImage()->fileName());
        $entity->setCreatedAt($product->getCreatedAt());
        $entity->setUpdatedAt($product->getUpdatedAt());

        return $entity;
    }
}
