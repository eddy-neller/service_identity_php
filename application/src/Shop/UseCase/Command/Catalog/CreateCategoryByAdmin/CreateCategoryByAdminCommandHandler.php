<?php

declare(strict_types=1);

namespace App\Application\Shop\UseCase\Command\Catalog\CreateCategoryByAdmin;

use App\Application\Shared\CQRS\Command\CommandHandlerInterface;
use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\SlugGeneratorInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CategoryRepositoryInterface;
use App\Domain\Shop\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Shop\Catalog\Exception\CategoryTitleAlreadyUsedException;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\ValueObject\CategoryDescription;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;

final readonly class CreateCategoryByAdminCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private CategoryRepositoryInterface $repository,
        private ClockInterface $clock,
        private TransactionalInterface $transactional,
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function handle(CreateCategoryByAdminCommand $command): CreateCategoryByAdminOutput
    {
        return $this->transactional->transactional(function () use ($command): CreateCategoryByAdminOutput {
            $now = $this->clock->now();
            $id = $this->repository->nextIdentity();
            $title = CategoryTitle::fromString($command->title);

            if (null !== $this->repository->findByTitle($title)) {
                throw new CategoryTitleAlreadyUsedException();
            }

            $slug = $this->slugGenerator->generate($title->toString());
            $description = CategoryDescription::fromNullableString($command->description);

            $parentId = $command->parentId;

            if (null !== $parentId) {
                $parent = $this->repository->findById($parentId);
                if (null === $parent) {
                    throw new CategoryNotFoundException('Parent category not found.', 404);
                }
            }

            $category = Category::create(
                id: $id,
                title: $title,
                slug: $slug,
                now: $now,
                parentId: $parentId,
                description: $description,
            );

            $this->repository->save($category);

            $categoryItem = $this->repository->findItemById($id);
            if (null === $categoryItem) {
                throw new CategoryNotFoundException();
            }

            return new CreateCategoryByAdminOutput($categoryItem);
        });
    }
}
