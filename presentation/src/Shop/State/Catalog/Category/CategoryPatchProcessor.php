<?php

declare(strict_types=1);

namespace App\Presentation\Shop\State\Catalog\Category;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\UseCase\Command\Catalog\UpdateCategoryByAdmin\UpdateCategoryByAdminCommand;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\Dto\Catalog\Category\CategoryPatchInput;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use LogicException;

final readonly class CategoryPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CategoryResourcePresenter $categoryResourcePresenter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CategoryResource
    {
        if (!$data instanceof CategoryPatchInput) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        if (!isset($uriVariables['id']) || !is_string($uriVariables['id'])) {
            throw new LogicException(PresentationErrorCode::INVALID_INPUT->value);
        }

        $parentId = null;
        if (null !== $data->parent) {
            $parentId = CategoryId::fromString($data->parent->id);
        }

        $categoryId = CategoryId::fromString($uriVariables['id']);

        $command = new UpdateCategoryByAdminCommand(
            categoryId: $categoryId,
            title: $data->title,
            description: $data->description,
            parentId: $parentId,
        );

        $output = $this->commandBus->dispatch($command);

        return $this->categoryResourcePresenter->toResource($output);
    }
}
