<?php

declare(strict_types=1);

namespace App\Domain\Shop\Catalog\Model;

use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductImage;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;

final class Product
{
    private function __construct(
        private ProductId $id,
        private ProductTitle $title,
        private ProductSubtitle $subtitle,
        private ProductDescription $description,
        private Money $price,
        private Slug $slug,
        private CategoryId $categoryId,
        private ProductImage $image,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        ProductId $id,
        ProductTitle $title,
        ProductSubtitle $subtitle,
        ProductDescription $description,
        Money $price,
        Slug $slug,
        CategoryId $categoryId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            title: $title,
            subtitle: $subtitle,
            description: $description,
            price: $price,
            slug: $slug,
            categoryId: $categoryId,
            image: new ProductImage(),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        ProductId $id,
        ProductTitle $title,
        ProductSubtitle $subtitle,
        ProductDescription $description,
        Money $price,
        Slug $slug,
        CategoryId $categoryId,
        ProductImage $image,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            title: $title,
            subtitle: $subtitle,
            description: $description,
            price: $price,
            slug: $slug,
            categoryId: $categoryId,
            image: $image,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function delete(DateTimeImmutable $now): void
    {
        $this->setUpdatedAt($now);
    }

    public function rename(ProductTitle $title, ProductSubtitle $subtitle, DateTimeImmutable $now): void
    {
        $this->setTitle($title);
        $this->setSubtitle($subtitle);
        $this->setUpdatedAt($now);
    }

    public function reprice(Money $price, DateTimeImmutable $now): void
    {
        $this->setPrice($price);
        $this->setUpdatedAt($now);
    }

    public function rewrite(ProductDescription $description, DateTimeImmutable $now): void
    {
        $this->setDescription($description);
        $this->setUpdatedAt($now);
    }

    public function moveToCategory(CategoryId $categoryId, DateTimeImmutable $now): void
    {
        $this->setCategoryId($categoryId);
        $this->setUpdatedAt($now);
    }

    public function reSlug(Slug $slug, DateTimeImmutable $now): void
    {
        $this->setSlug($slug);
        $this->setUpdatedAt($now);
    }

    public function updateImage(ProductImage $image, DateTimeImmutable $now): void
    {
        $this->setImage($image);
        $this->setUpdatedAt($now);
    }

    public function getId(): ProductId
    {
        return $this->id;
    }

    public function getTitle(): ProductTitle
    {
        return $this->title;
    }

    public function getSubtitle(): ProductSubtitle
    {
        return $this->subtitle;
    }

    public function getDescription(): ProductDescription
    {
        return $this->description;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function getSlug(): Slug
    {
        return $this->slug;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getImage(): ProductImage
    {
        return $this->image;
    }

    public function getImageName(): ?string
    {
        return $this->image->fileName();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function setTitle(ProductTitle $title): void
    {
        $this->title = $title;
    }

    private function setSubtitle(ProductSubtitle $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    private function setDescription(ProductDescription $description): void
    {
        $this->description = $description;
    }

    private function setPrice(Money $price): void
    {
        $this->price = $price;
    }

    private function setSlug(Slug $slug): void
    {
        $this->slug = $slug;
    }

    private function setCategoryId(CategoryId $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    private function setImage(ProductImage $image): void
    {
        $this->image = $image;
    }

    private function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
