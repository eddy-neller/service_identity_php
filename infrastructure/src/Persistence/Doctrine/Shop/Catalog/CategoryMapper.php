<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Catalog;

use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Category as DomainCategory;
use App\Domain\Shop\Catalog\ValueObject\CategoryDescription;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Infrastructure\Entity\Shop\Category as DoctrineCategory;
use Ramsey\Uuid\Uuid;

final readonly class CategoryMapper
{
    public function toDomain(DoctrineCategory $entity): DomainCategory
    {
        $parent = $entity->getParent();

        return DomainCategory::reconstitute(
            id: CategoryId::fromString($entity->getId()->toString()),
            title: CategoryTitle::fromString($entity->getTitle()),
            slug: Slug::fromString($entity->getSlug()),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            parentId: null === $parent ? null : CategoryId::fromString($parent->getId()->toString()),
            description: CategoryDescription::fromNullableString($entity->getDescription()),
            productCount: $entity->getNbProduct(),
            level: $entity->getLevel(),
            hasChildren: $entity->hasChildren(),
        );
    }

    public function toDoctrine(DomainCategory $category, ?DoctrineCategory $entity = null): DoctrineCategory
    {
        $entity ??= new DoctrineCategory();

        $entity->setId(Uuid::fromString($category->getId()->toString()));
        $entity->setTitle($category->getTitle()->toString());
        $entity->setDescription($category->getDescription()?->toString());
        $entity->setSlug($category->getSlug()->toString());
        $entity->setNbProduct($category->getProductCount());

        return $entity;
    }
}
