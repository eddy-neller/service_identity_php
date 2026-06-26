<?php

declare(strict_types=1);

namespace App\Application\Tests\Unit\Shop\UseCase\Command\Ordering;

use App\Application\Shared\Port\ClockInterface;
use App\Application\Shared\Port\TransactionalInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Application\Shop\Port\ProductImageUrlResolverInterface;
use App\Application\Shop\Port\ProductRepositoryInterface;
use App\Application\Shop\ReadModel\Ordering\CartItem;
use App\Application\Shop\Service\CartItemFactory;
use App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine\UpdateCartLineCommand;
use App\Application\Shop\UseCase\Command\Ordering\UpdateCartLine\UpdateCartLineCommandHandler;
use App\Domain\Shop\Catalog\ValueObject\ProductId;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Exception\CartLineNotFoundException;
use App\Domain\Shop\Ordering\Model\Cart;
use App\Domain\Shop\Ordering\Model\CartLine;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateCartLineTest extends TestCase
{
    private const string CART_ID = '550e8400-e29b-41d4-a716-446655440130';

    private const string CART_LINE_ID = '550e8400-e29b-41d4-a716-446655440131';

    private const string CUSTOMER_ID = '550e8400-e29b-41d4-a716-446655440132';

    private const string PRODUCT_ID = '550e8400-e29b-41d4-a716-446655440133';

    private CartRepositoryInterface&MockObject $repository;

    private ProductRepositoryInterface&MockObject $productRepository;

    private ClockInterface&MockObject $clock;

    private TransactionalInterface&MockObject $transactional;

    private UpdateCartLineCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $imageResolver = $this->createMock(ProductImageUrlResolverInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->transactional = $this->createMock(TransactionalInterface::class);

        $imageResolver->expects($this->never())->method('resolve');

        $this->handler = new UpdateCartLineCommandHandler(
            $this->repository,
            new CartItemFactory($this->productRepository, $imageResolver),
            $this->clock,
            $this->transactional,
        );
    }

    public function testHandleThrowsWhenNoCartExists(): void
    {
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $command = new UpdateCartLineCommand($customerId, ProductId::fromString(self::PRODUCT_ID), 3);

        $this->repository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn(null);

        $this->clock->expects($this->never())->method('now');
        $this->productRepository->expects($this->never())->method('findByIds');
        $this->repository->expects($this->never())->method('save');
        $this->expectTransaction();

        $this->expectException(CartLineNotFoundException::class);
        $this->expectExceptionMessage('Cart line not found.');

        $this->handler->handle($command);
    }

    public function testHandleUpdatesLineQuantity(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart = $this->cartWithLine($customerId, $productId);
        $command = new UpdateCartLineCommand($customerId, $productId, 5);

        $this->repository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn($cart);

        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);
        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $saved) use ($productId, $now): bool {
                $lines = $saved->getLines();

                return 1 === count($lines)
                    && $lines[0]->getProductId()->equals($productId)
                    && 5 === $lines[0]->getQuantity()
                    && $saved->getUpdatedAt() === $now;
            }));

        $this->expectTransaction();

        $output = $this->handler->handle($command);

        $this->assertInstanceOf(CartItem::class, $output);
        $this->assertSame(self::CART_ID, $output->id);
    }

    public function testHandleRemovesLineWhenQuantityIsZero(): void
    {
        $now = new DateTimeImmutable('2025-01-01 10:00:00');
        $customerId = CustomerId::fromString(self::CUSTOMER_ID);
        $productId = ProductId::fromString(self::PRODUCT_ID);
        $cart = $this->cartWithLine($customerId, $productId);
        $command = new UpdateCartLineCommand($customerId, $productId, 0);

        $this->repository->expects($this->once())
            ->method('findByOwnerForUpdate')
            ->with($customerId)
            ->willReturn($cart);

        $this->clock->expects($this->atLeastOnce())->method('now')->willReturn($now);
        $this->productRepository->expects($this->once())->method('findByIds')->willReturn([]);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Cart $saved) use ($now): bool {
                return [] === $saved->getLines()
                    && $saved->getUpdatedAt() === $now;
            }));

        $this->expectTransaction();

        $output = $this->handler->handle($command);

        $this->assertInstanceOf(CartItem::class, $output);
    }

    private function cartWithLine(CustomerId $customerId, ProductId $productId): Cart
    {
        return Cart::reconstitute(
            CartId::fromString(self::CART_ID),
            $customerId,
            [CartLine::create(CartLineId::fromString(self::CART_LINE_ID), $productId, 2)],
            new DateTimeImmutable('2025-01-01 09:00:00'),
            new DateTimeImmutable('2025-01-01 09:00:00'),
        );
    }

    private function expectTransaction(): void
    {
        $this->transactional->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());
    }
}
