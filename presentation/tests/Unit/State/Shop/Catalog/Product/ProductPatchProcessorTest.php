<?php

declare(strict_types=1);

namespace App\Presentation\Tests\Unit\State\Shop\Catalog\Product;

use ApiPlatform\Metadata\Operation;
use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\ReadModel\Catalog\ProductItem;
use App\Application\Shop\UseCase\Command\Catalog\UpdateProductByAdmin\UpdateProductByAdminCommand;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Model\Category;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\CategoryTitle;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductImage;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Shared\ValueObject\Money;
use App\Presentation\Shared\State\PresentationErrorCode;
use App\Presentation\Shop\ApiResource\Catalog\CategoryResource;
use App\Presentation\Shop\ApiResource\Catalog\ProductResource;
use App\Presentation\Shop\Dto\Catalog\Product\ProductPatchInput;
use App\Presentation\Shop\Presenter\Catalog\CategoryResourcePresenter;
use App\Presentation\Shop\Presenter\Catalog\ProductResourcePresenter;
use App\Presentation\Shop\State\Catalog\Product\ProductPatchProcessor;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ProductPatchProcessorTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;

    private ProductImageUrlResolverInterface&MockObject $productImageUrlResolver;

    private Operation&MockObject $operation;

    private ProductPatchProcessor $processor;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->productImageUrlResolver = $this->createMock(ProductImageUrlResolverInterface::class);
        $this->operation = $this->createMock(Operation::class);
        $this->operation->expects($this->never())
            ->method('getName');

        $presenter = new ProductResourcePresenter(
            $this->productImageUrlResolver,
            new CategoryResourcePresenter(),
        );

        $this->processor = new ProductPatchProcessor(
            $this->commandBus,
            $presenter,
        );
    }

    public function testProcessWithValidInput(): void
    {
        $input = new ProductPatchInput();
        $input->title = 'Updated product';
        $input->subtitle = 'Updated subtitle';
        $input->description = 'Updated description';
        $input->price = 29.99;
        $input->category = $this->createCategoryResource('550e8400-e29b-41d4-a716-446655440001');

        $output = $this->createProductView();
        $productId = '550e8400-e29b-41d4-a716-446655440000';

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use ($input, $productId, $output): ProductItem {
                $this->assertInstanceOf(UpdateProductByAdminCommand::class, $command);
                $this->assertSame($productId, $command->productId);
                $this->assertSame($input->title, $command->title);
                $this->assertSame($input->subtitle, $command->subtitle);
                $this->assertSame($input->description, $command->description);
                $this->assertSame($input->price, $command->price);
                $this->assertSame($input->category->id, $command->categoryId);

                return $output;
            });

        $this->productImageUrlResolver->expects($this->once())
            ->method('resolve')
            ->with('product.jpg')
            ->willReturn('/uploads/product.jpg');

        $result = $this->processor->process($input, $this->operation, ['id' => $productId]);

        $this->assertInstanceOf(ProductResource::class, $result);
        $this->assertSame('Updated product', $result->title);
    }

    public function testProcessThrowsLogicExceptionForInvalidInput(): void
    {
        $invalidInput = new stdClass();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->productImageUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($invalidInput, $this->operation, ['id' => 'id']);
    }

    public function testProcessThrowsLogicExceptionWhenIdIsMissing(): void
    {
        $input = new ProductPatchInput();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->productImageUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($input, $this->operation, []);
    }

    public function testProcessThrowsLogicExceptionWhenIdIsNotString(): void
    {
        $input = new ProductPatchInput();

        $this->commandBus->expects($this->never())
            ->method('dispatch');
        $this->productImageUrlResolver->expects($this->never())
            ->method('resolve');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(PresentationErrorCode::INVALID_INPUT->value);

        $this->processor->process($input, $this->operation, ['id' => 123]);
    }

    private function createProductView(): ProductItem
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');
        $category = Category::create(
            id: CategoryId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            title: CategoryTitle::fromString('Category title'),
            slug: Slug::fromString('category-title'),
            now: $now,
        );

        $product = Product::reconstitute(
            id: ProductId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            title: ProductTitle::fromString('Updated product'),
            subtitle: ProductSubtitle::fromString('Updated subtitle'),
            description: ProductDescription::fromString('Updated description'),
            price: Money::fromInt(2999),
            slug: Slug::fromString('updated-product'),
            categoryId: CategoryId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            image: ProductImage::create('product.jpg'),
            createdAt: $now,
            updatedAt: $now,
        );

        return ProductItem::fromProduct($product, $category);
    }

    private function createCategoryResource(string $id): CategoryResource
    {
        $resource = new CategoryResource();
        $resource->id = $id;

        return $resource;
    }
}
