<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateCategoryByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\CatalogDomainException;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\CategoryTitleAlreadyUsedException;
use App\Domain\Shop\Catalog\ValueObject\CategoryDescription;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;

final readonly class UpdateCategoryByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function handle(UpdateCategoryByAdminCommand $command): CategoryItem
    {
        $categoryId = CategoryId::fromString($command->categoryId);
        $parentId = null !== $command->parentId ? CategoryId::fromString($command->parentId) : null;
        $title = null !== $command->title ? CategoryTitle::fromString($command->title) : null;
        $slug = null !== $title ? $this->slugGenerator->generate($title->toString()) : null;
        $description = null !== $command->description
            ? CategoryDescription::fromString($command->description)
            : null;

        if (null !== $parentId && $categoryId->equals($parentId)) {
            throw new CatalogDomainException('Category cannot be its own parent.', 400);
        }

        return $this->transactional->transactional(
            fn (): CategoryItem => $this->updateCategory($categoryId, $parentId, $title, $slug, $description),
        );
    }

    private function updateCategory(
        CategoryId $categoryId,
        ?CategoryId $parentId,
        ?CategoryTitle $title,
        ?Slug $slug,
        ?CategoryDescription $description,
    ): CategoryItem {
        $category = $this->repository->findById($categoryId);

        if (null === $category) {
            throw new CategoryNotFoundException();
        }

        if (null !== $title) {
            $existing = $this->repository->findByTitle($title);
            if (null !== $existing && !$existing->getId()->equals($category->getId())) {
                throw new CategoryTitleAlreadyUsedException();
            }
        }

        if (null !== $parentId) {
            $parent = $this->repository->findById($parentId);
            if (null === $parent) {
                throw new CategoryNotFoundException('Parent category not found.', 404);
            }
        }

        $now = $this->clock->now();

        if (null !== $title && null !== $slug) {
            $category->rename($title, $slug, $now);
        }

        if (null !== $description) {
            $category->describe($description, $now);
        }

        if (null !== $parentId) {
            $category->moveTo($parentId, $now);
        }

        $this->repository->save($category);

        $categoryItem = $this->repository->findItemById($categoryId);

        if (null === $categoryItem) {
            throw new CategoryNotFoundException();
        }

        return $categoryItem;
    }
}
