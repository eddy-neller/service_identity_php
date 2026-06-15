<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Ordering;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\Service\CartItemFactory;
use App\Application\Shop\UseCase\Command\Ordering\AddToCart\AddToCartCommand;
use App\Application\Shop\UseCase\Command\Ordering\AddToCart\AddToCartCommandHandler;
use App\Application\Shop\UseCase\Command\Ordering\AddToCart\AddToCartOutput;
use App\Domain\SharedKernel\ValueObject\Slug;
use App\Domain\Shop\Catalog\Exception\ProductNotFoundException;
use App\Domain\Shop\Catalog\Model\Product;
use App\Domain\Shop\Catalog\ValueObject\CategoryId;
use App\Domain\Shop\Catalog\ValueObject\ProductDescription;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Catalog\ValueObject\ProductSubtitle;
use App\Domain\Shop\Catalog\ValueObject\ProductTitle;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\Model\CartLine;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Domain\Shop\Shared\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddToCartTest extends TestCase
{
    private const string CART_ID = '550e8400-e29b-41d4-a716-446655440100';

    private const string CART_LINE_ID = '550e8400-e29b-41d4-a716-446655440101';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440102';

    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440103';

    private CartRepositoryInterface&MockObject $cartRepository;

    private ProductRepositoryInterface&MockObject $productRepository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private AddToCartCommandHandler $handler;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $imageResolver = $this->createMock(ProductImageUrlResolverInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);

        $imageResolver->expects($this->never())->method('resolve');

        $this->handler = new AddToCartCommandHandler(
            $this->cartRepository,
            $this->productRepository,
            new CartItemFactory($this->productRepository, $imageResolver),
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleThrowsWhenProductNotFound(): void
    {
        $command = new AddToCartCommand(
            CustomerId::fromString(self::CUSTOMER_ID),
            ProductId::fromString(self::PRODUCT_ID),
            2,
        );

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($command->productId)
            ->willReturn(null);

        $this->clock->expects($this->never())->method('now');
        $this->cartRepository->expects($this->never())->method('save');
        $this->expectTransaction();

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product not found.');

        $this->handler->handle($command);
    }

    public function testHandleCreatesCartWhenNoneExists(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $cartId = CartId::fromString(self::CART_ID);
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $command = new AddToCartCommand($customerId, $productId, 3);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($this->createProduct($productId));

        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $this->cartRepository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(null);

        $this->cartRepository->expects($this->once())->method('nextIdentity')->willReturn($cartId);
        $this->cartRepository->expects($this->once())
            ->method('nextLineIdentity')
            ->willReturn(CartLineId::fromString(self::CART_LINE_ID));
        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $cart) use ($cartId, $customerId, $productId, $now): bool {
                $lines = $cart->getLines();

                return $cart->getId()->equals($cartId)
                    && $cart->getOwnerId()->equals($customerId)
                    && 1 === count($lines)
                    && $lines[0]->getProductId()->equals($productId)
                    && 3 === $lines[0]->getQuantity()
                    && $cart->getCreatedAt() === $now
                    && $cart->getUpdatedAt() === $now;
            }));

        $this->expectTransaction();

        $output = $this->handler->handle($command);

        $this->assertInstanceOf(AddToCartOutput::class, $output);
        $this->assertSame(self::CART_ID, $output->cart->id);
    }

    public function testHandleAddsLineToExistingCart(): void
    {
        $now = new DateTimeImmutable('2025-01-01 11:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart = Cart::create(CartId::fromString(self::CART_ID), $customerId, new DateTimeImmutable('2025-01-01 09:00:00'));
        $command = new AddToCartCommand($customerId, $productId, 2);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($this->createProduct($productId));

        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $this->cartRepository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn($cart);

        $this->cartRepository->expects($this->never())->method('nextIdentity');
        $this->cartRepository->expects($this->once())
            ->method('nextLineIdentity')
            ->willReturn(CartLineId::fromString(self::CART_LINE_ID));
        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $saved) use ($cart, $productId, $now): bool {
                $lines = $saved->getLines();

                return $saved === $cart
                    && 1 === count($lines)
                    && $lines[0]->getProductId()->equals($productId)
                    && 2 === $lines[0]->getQuantity()
                    && $saved->getUpdatedAt() === $now;
            }));

        $this->expectTransaction();

        $this->handler->handle($command);
    }

    public function testHandleIncrementsQuantityWhenProductAlreadyInCart(): void
    {
        $now = new DateTimeImmutable('2025-01-01 12:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart = Cart::reconstitute(
            CartId::fromString(self::CART_ID),
            $customerId,
            [CartLine::create(CartLineId::fromString(self::CART_LINE_ID), $productId, 2)],
            new DateTimeImmutable('2025-01-01 09:00:00'),
            new DateTimeImmutable('2025-01-01 09:00:00'),
        );
        $command = new AddToCartCommand($customerId, $productId, 3);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($this->createProduct($productId));

        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $this->cartRepository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn($cart);

        $this->cartRepository->expects($this->once())
            ->method('nextLineIdentity')
            ->willReturn(CartLineId::fromString(self::CART_LINE_ID));
        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);

        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $saved) use ($productId): bool {
                $lines = $saved->getLines();

                return 1 === count($lines)
                    && $lines[0]->getProductId()->equals($productId)
                    && 5 === $lines[0]->getQuantity();
            }));

        $this->expectTransaction();

        $this->handler->handle($command);
    }

    private function expectTransaction(): void
    {
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());
    }

    private function createProduct(ProductId $productId): Product
    {
        return Product::create(
            id: $productId,
            title: ProductTitle::fromString('Product title'),
            subtitle: ProductSubtitle::fromString('Product subtitle'),
            description: ProductDescription::fromString('Product description'),
            price: Money::fromInt(1299),
            slug: Slug::fromString('product-title'),
            categoryId: CategoryId::fromString('550e8400-e29b-41d4-a716-446655440104'),
            now: new DateTimeImmutable('2024-01-01 09:00:00'),
        );
    }
}
