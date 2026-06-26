<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\UpdateCategoryByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Application\Shop\ReadModel\Catalog\CategoryItem;
use App\Domain\Shop\Catalog\Exception\CatalogDomainException;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\CategoryTitleAlreadyUsedException;
use App\Domain\Shop\Catalog\ValueObject\CategoryDescription;
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
        $category = $this->repository->findById($command->categoryId);

        if (null === $category) {
            throw new CategoryNotFoundException();
        }

        return $this->transactional->transactional(function () use ($category, $command): CategoryItem {
            $now = $this->clock->now();

            if (null !== $command->title) {
                $title = CategoryTitle::fromString($command->title);

                $existing = $this->repository->findByTitle($title);
                if (null !== $existing && !$existing->getId()->equals($category->getId())) {
                    throw new CategoryTitleAlreadyUsedException();
                }

                $slug = $this->slugGenerator->generate($title->toString());
                $category->rename($title, $slug, $now);
            }

            if (null !== $command->description) {
                $category->describe(CategoryDescription::fromString($command->description), $now);
            }

            if (null !== $command->parentId) {
                if ($command->categoryId->equals($command->parentId)) {
                    throw new CatalogDomainException('Category cannot be its own parent.', 400);
                }

                $parent = $this->repository->findById($command->parentId);
                if (null === $parent) {
                    throw new CategoryNotFoundException('Parent category not found.', 404);
                }

                $category->moveTo($command->parentId, $now);
            }

            $this->repository->save($category);

            $categoryItem = $this->repository->findItemById($command->categoryId);
            if (null === $categoryItem) {
                throw new CategoryNotFoundException();
            }

            return $categoryItem;
        });
    }
}
